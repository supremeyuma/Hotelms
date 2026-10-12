// Learn more about Tauri commands at https://tauri.app/develop/calling-rust/
pub mod envfile;
pub mod postgres;
pub mod runtime;
pub mod setup;

use std::path::Path;
use std::sync::atomic::{AtomicBool, Ordering};
use std::sync::{Arc, Mutex};

use serde::{Deserialize, Serialize};
use tauri::{AppHandle, Emitter, Manager, State};

use crate::setup::state::SetupState;
use crate::setup::{AdminCredentials, ClientOptions, ServerBoot, ServerOptions, TerminalOptions};

/// The desktop app's own bundle layout is under `resources/` in development,
/// and under the resource directory once Task 7 packages the runtime and the
/// application into the installer.
const DEV_TRIPLE: &str = if cfg!(target_os = "windows") {
    "x86_64-pc-windows-msvc"
} else {
    "host"
};

/// Emitted for every step of a setup run. The wizard updates its progress
/// indicator from these and never guesses a step is stuck.
const EVT_STEP: &str = "setup://step";
/// Emitted once, at the end of a run, with `{ ok: true, ... }` or
/// `{ ok: false, error }`. The wizard uses this to leave the progress screen.
const EVT_DONE: &str = "setup://done";

/// Managed state: owns the booted server so its child processes survive the
/// setup command, and guards against double submission (the UI must never be
/// able to start two setup runs at once).
#[derive(Default)]
struct SetupHandle {
    running: AtomicBool,
    active: Mutex<Option<ServerBoot>>,
}

#[tauri::command]
fn greet(name: &str) -> String {
    format!("Hello, {}! You've been greeted from Rust!", name)
}

/// The ordered steps for a role, so the wizard can render them before running.
#[tauri::command]
fn setup_plan(role: &str) -> Result<Vec<setup::StepView>, String> {
    let role = match role {
        "server" => setup::SetupRole::Server,
        "terminal" => setup::SetupRole::Terminal,
        "staff_client" => setup::SetupRole::StaffClient,
        other => return Err(format!("unknown setup role: {other}")),
    };

    Ok(setup::plan_views(role))
}

/// Whether a LAN server is currently booted and being served.
#[tauri::command]
fn setup_server_active(state: State<'_, Arc<SetupHandle>>) -> bool {
    server_active(&state)
}

fn server_active(state: &Arc<SetupHandle>) -> bool {
    state
        .active
        .lock()
        .map(|active| active.is_some())
        .unwrap_or(false)
}

/// Everything the wizard needs to render resume state: whether a run is in
/// flight, whether a server is already booted, and which steps have completed.
#[derive(Serialize)]
#[serde(rename_all = "camelCase")]
struct SetupStatusView {
    running: bool,
    server_active: bool,
    completed: Vec<String>,
}

#[tauri::command]
fn setup_status(app: AppHandle, state: State<'_, Arc<SetupHandle>>) -> SetupStatusView {
    let completed = resolve_paths(&app)
        .ok()
        .and_then(|paths| SetupState::load(&paths.data_dir).ok())
        .map(|record| record.completed())
        .unwrap_or_default();

    SetupStatusView {
        running: state.running.load(Ordering::SeqCst),
        server_active: server_active(&state),
        completed,
    }
}

#[derive(Deserialize)]
#[serde(rename_all = "camelCase")]
struct ServerSetupInput {
    admin_name: String,
    admin_email: String,
    admin_password: String,
    property_name: Option<String>,
    http_port: Option<u16>,
    db_port: Option<u16>,
}

/// Boot a LAN server in the background. Returns immediately; progress is
/// streamed over `setup://step` and the outcome over `setup://done`.
#[tauri::command]
fn setup_run_server(
    app: AppHandle,
    state: State<'_, Arc<SetupHandle>>,
    input: ServerSetupInput,
) -> Result<(), String> {
    if input.admin_name.trim().is_empty()
        || input.admin_email.trim().is_empty()
        || input.admin_password.is_empty()
    {
        return Err("admin name, email and password are required".into());
    }

    if state.running.swap(true, Ordering::SeqCst) {
        return Err("setup is already running".into());
    }

    let paths = match resolve_paths(&app) {
        Ok(paths) => paths,
        Err(error) => {
            state.running.store(false, Ordering::SeqCst);
            return Err(error);
        }
    };

    let options = ServerOptions {
        http_port: input.http_port.unwrap_or(setup::DEFAULT_HTTP_PORT),
        db_port: input.db_port.unwrap_or(setup::DEFAULT_DB_PORT),
        admin: AdminCredentials {
            name: input.admin_name,
            email: input.admin_email,
            password: input.admin_password,
            property: input.property_name,
        },
    };

    let handle = Arc::clone(&state);
    tauri::async_runtime::spawn_blocking(move || {
        let port = options.http_port;
        let result = setup::run_server(&paths, &options, |event| {
            let _ = app.emit(EVT_STEP, &event);
        });

        match result {
            Ok(boot) => {
                *handle.active.lock().expect("setup state poisoned") = Some(boot);
                emit_done(&app, Ok(serde_json::json!({ "ok": true, "port": port })));
            }
            Err(error) => emit_done(&app, Err(error.to_string())),
        }
        handle.running.store(false, Ordering::SeqCst);
    });

    Ok(())
}

#[derive(Deserialize)]
#[serde(rename_all = "camelCase")]
struct TerminalSetupInput {
    property_code: String,
    pairing_code: String,
    http_port: Option<u16>,
}

/// Create and migrate a terminal's local SQLite database in the background.
#[tauri::command]
fn setup_run_terminal(
    app: AppHandle,
    state: State<'_, Arc<SetupHandle>>,
    input: TerminalSetupInput,
) -> Result<(), String> {
    if state.running.swap(true, Ordering::SeqCst) {
        return Err("setup is already running".into());
    }

    let paths = match resolve_paths(&app) {
        Ok(paths) => paths,
        Err(error) => {
            state.running.store(false, Ordering::SeqCst);
            return Err(error);
        }
    };

    let options = TerminalOptions {
        property_code: input.property_code,
        pairing_code: input.pairing_code,
        http_port: input.http_port.unwrap_or(setup::DEFAULT_HTTP_PORT),
    };

    let handle = Arc::clone(&state);
    tauri::async_runtime::spawn_blocking(move || {
        let result = setup::run_terminal(&paths, &options, |event| {
            let _ = app.emit(EVT_STEP, &event);
        });
        match result {
            Ok(_boot) => emit_done(&app, Ok(serde_json::json!({ "ok": true }))),
            Err(error) => emit_done(&app, Err(error.to_string())),
        }
        handle.running.store(false, Ordering::SeqCst);
    });

    Ok(())
}

#[derive(Deserialize)]
#[serde(rename_all = "camelCase")]
struct ClientSetupInput {
    server_url: String,
}

/// Point a staff client at a server. No database, no migrations — it only
/// writes the environment. Runs in the background for a consistent event flow.
#[tauri::command]
fn setup_run_staff_client(
    app: AppHandle,
    state: State<'_, Arc<SetupHandle>>,
    input: ClientSetupInput,
) -> Result<(), String> {
    if input.server_url.trim().is_empty() {
        return Err("a server address is required".into());
    }

    if state.running.swap(true, Ordering::SeqCst) {
        return Err("setup is already running".into());
    }

    let paths = match resolve_paths(&app) {
        Ok(paths) => paths,
        Err(error) => {
            state.running.store(false, Ordering::SeqCst);
            return Err(error);
        }
    };

    let options = ClientOptions {
        server_url: input.server_url,
    };

    let handle = Arc::clone(&state);
    tauri::async_runtime::spawn_blocking(move || {
        let result = setup::run_staff_client(&paths, &options, |event| {
            let _ = app.emit(EVT_STEP, &event);
        });
        match result {
            Ok(boot) => emit_done(
                &app,
                Ok(serde_json::json!({ "ok": true, "serverUrl": boot.server_url })),
            ),
            Err(error) => emit_done(&app, Err(error.to_string())),
        }
        handle.running.store(false, Ordering::SeqCst);
    });

    Ok(())
}

fn emit_done(app: &AppHandle, result: Result<serde_json::Value, String>) {
    let payload = match result {
        Ok(value) => value,
        Err(error) => serde_json::json!({ "ok": false, "error": error }),
    };
    let _ = app.emit(EVT_DONE, payload);
}

/// Resolve the engine's `Paths` from the app: the bundled resource dir when the
/// runtime and application are packaged (Task 7), the repository otherwise.
fn resolve_paths(app: &AppHandle) -> Result<setup::Paths, String> {
    let manifest = Path::new(env!("CARGO_MANIFEST_DIR"));
    let repo_root = manifest.join("..").join("..");

    let dev_runtime = repo_root.join(format!("resources/bin/{DEV_TRIPLE}"));
    let dev_postgres = repo_root.join(format!("resources/postgres/{DEV_TRIPLE}/pgsql"));
    let dev_ini = repo_root.join("resources/runtime/php.ini");

    let frankenphp = if cfg!(windows) {
        "frankenphp.exe"
    } else {
        "frankenphp"
    };

    let resource = app
        .path()
        .resource_dir()
        .map_err(|error| format!("no resource dir: {error}"))?;

    let runtime_dir = resource.join("runtime");
    let runtime_dir = if runtime_dir.join(frankenphp).exists() {
        runtime_dir
    } else {
        dev_runtime
    };

    let app_root = resource.join("app");
    let app_root = if app_root.join("artisan").exists() {
        app_root
    } else {
        repo_root
    };

    let postgres_bundle = resource.join("postgres");
    let postgres_bundle = if postgres_bundle.join("bin").exists() {
        postgres_bundle
    } else {
        dev_postgres
    };

    let data_dir = app
        .path()
        .app_data_dir()
        .map_err(|error| format!("no app data dir: {error}"))?;

    Ok(setup::Paths {
        data_dir,
        app_root,
        runtime_dir,
        php_ini: dev_ini,
        postgres_bundle,
    })
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_opener::init())
        .manage(Arc::new(SetupHandle::default()))
        .invoke_handler(tauri::generate_handler![
            greet,
            setup_plan,
            setup_server_active,
            setup_status,
            setup_run_server,
            setup_run_terminal,
            setup_run_staff_client
        ])
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}
