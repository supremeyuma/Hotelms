// Learn more about Tauri commands at https://tauri.app/develop/calling-rust/
pub mod discovery;
pub mod envfile;
pub mod paths;
pub mod postgres;
pub mod runtime;
pub mod setup;

use std::path::Path;
use std::sync::atomic::{AtomicBool, Ordering};
use std::sync::{Arc, Mutex};
use std::time::Duration;

use serde::{Deserialize, Serialize};
use tauri::menu::{MenuBuilder, MenuItemBuilder};
use tauri::tray::{TrayIconBuilder, TrayIconEvent};
use tauri::{AppHandle, Emitter, Manager, State, WindowEvent};

use crate::discovery::DiscoveredServer;
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

    // Stop any server this process still owns *before* the new run starts.
    //
    // Dropping a previous boot stops its Postgres with `pg_ctl -D <data dir>
    // stop`, and a second run uses the same data directory — so dropping it
    // after the new cluster was up would stop the cluster that just booted.
    // Storing the fresh boot would then leave a live-looking setup with a dead
    // database. Doing it first also makes a retry mean "restart the server",
    // which is what an operator pressing the button again expects.
    if let Ok(mut active) = state.active.lock() {
        *active = None;
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
                // A booted server must answer discovery probes so other roles
                // can find it without typing an address.
                let _ = discovery::respond("MooreLife", env!("CARGO_PKG_VERSION"), port);
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
    server_url: Option<String>,
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
        server_url: input.server_url,
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

/// Stop the server this process owns, if any.
///
/// Auto-update installs a new build over the installed tree; Postgres and
/// FrankenPHP are children of this process and hold open files inside that
/// tree, so the installer cannot replace them while they run. Dropping
/// `ServerBoot` stops both in order. Safe when nothing is running.
#[tauri::command]
fn setup_stop_server(state: State<'_, Arc<SetupHandle>>) -> Result<(), String> {
    let mut active = state
        .active
        .lock()
        .map_err(|_| "setup state poisoned".to_string())?;

    *active = None;
    Ok(())
}

/// Whether the start-up update check should run.
///
/// The native e2e suite sets `MOORELIFE_DISABLE_UPDATES=1`: an update banner
/// would otherwise be able to intercept clicks in a build that is
/// intentionally older than the published release.
#[tauri::command]
fn updates_enabled() -> bool {
    std::env::var("MOORELIFE_DISABLE_UPDATES").is_err()
}

fn emit_done(app: &AppHandle, result: Result<serde_json::Value, String>) {
    let payload = match result {
        Ok(value) => value,
        Err(error) => serde_json::json!({ "ok": false, "error": error }),
    };
    let _ = app.emit(EVT_DONE, payload);
}

/// One server found by the broadcast probe.
#[derive(Serialize)]
#[serde(rename_all = "camelCase")]
struct DiscoveredServerView {
    address: String,
    name: String,
    version: String,
    http_port: u16,
}

/// Broadcast the discovery probe and return every server that answered. The
/// wizard uses this to fill in the server address; when broadcast is blocked,
/// it falls back to manual entry or the pairing QR code.
#[tauri::command]
fn discovery_probe() -> Result<Vec<DiscoveredServerView>, String> {
    let found = discovery::probe(Duration::from_secs(2)).map_err(|error| error.to_string())?;
    Ok(found.into_iter().map(Into::into).collect())
}

impl From<DiscoveredServer> for DiscoveredServerView {
    fn from(server: DiscoveredServer) -> Self {
        Self {
            address: server.address.to_string(),
            name: server.name,
            version: server.version,
            http_port: server.http_port,
        }
    }
}

/// Everything the server wizard renders as the pairing fallback: the LAN
/// addresses and the pairing URIs each one encodes.
#[derive(Serialize)]
#[serde(rename_all = "camelCase")]
struct PairingView {
    addresses: Vec<String>,
    uris: Vec<String>,
    name: String,
    version: String,
    port: u16,
}

#[tauri::command]
fn discovery_pairing(state: State<'_, Arc<SetupHandle>>) -> Result<PairingView, String> {
    let port = state
        .active
        .lock()
        .expect("setup state poisoned")
        .as_ref()
        .and_then(server_http_port)
        .ok_or_else(|| "no server is booted".to_string())?;

    let addresses = discovery::lan_addresses().map_err(|error| error.to_string())?;
    let name = "MooreLife".to_string();
    let version = env!("CARGO_PKG_VERSION").to_string();
    let uris = addresses
        .iter()
        .map(|ip| discovery::pairing_uri(*ip, &name, &version, port))
        .collect();

    Ok(PairingView {
        addresses: addresses.iter().map(ToString::to_string).collect(),
        uris,
        name,
        version,
        port,
    })
}

/// The port the booted server serves HTTP on, as written into its own
/// environment (`FRANKENPHP_PORT`, set by the server env profile).
fn server_http_port(boot: &ServerBoot) -> Option<u16> {
    boot.env
        .iter()
        .find(|(key, _)| key == "FRANKENPHP_PORT")
        .and_then(|(_, value)| value.parse().ok())
}

/// Resolve the engine's `Paths` from the app: the bundled resource dir when the
/// runtime and application are packaged (Task 7), the repository otherwise.
fn resolve_paths(app: &AppHandle) -> Result<setup::Paths, String> {
    let manifest = Path::new(env!("CARGO_MANIFEST_DIR"));
    let repo_root = manifest.join("..").join("..");

    let dev_runtime = repo_root.join(format!("resources/bin/{DEV_TRIPLE}"));
    let dev_postgres = repo_root.join(format!("resources/postgres-runtime/{DEV_TRIPLE}/pgsql"));
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

    let php_ini = resource.join("runtime").join("php.ini");
    let php_ini = if php_ini.exists() { php_ini } else { dev_ini };

    // A bundled application is only usable when the entry script PHP opens is
    // actually present. An `artisan` file alone is not enough: a half-copied
    // resource tree would otherwise boot a server that answers every request
    // with "Failed opening required .../public/index.php".
    //
    // A build that still lives in a Cargo output directory is a *development*
    // build, and `target/<profile>/app` is a copy made at some earlier point.
    // Preferring that copy over the repository silently serves stale code: the
    // e2e suite saw an old tree with 174 migrations instead of the 177 in the
    // repo, and the missing built assets made `/login` fail. A real installed
    // app has no `.cargo-lock` beside the executable and no repository, so it
    // keeps using its bundled tree.
    let resource = paths::simplify(&resource);
    let running_from_cargo_output = resource.join(".cargo-lock").exists()
        || resource.join(".cargo-build-lock").exists();

    let bundled_app = resource.join("app");
    let bundled_usable = bundled_app.join("public").join("index.php").exists();
    let repo_usable = repo_root.join("public").join("index.php").exists()
        && repo_root.join("vendor").join("autoload.php").exists();

    let app_root = if running_from_cargo_output && repo_usable {
        repo_root
    } else if bundled_usable {
        bundled_app
    } else if repo_usable {
        repo_root
    } else {
        return Err(format!(
            "the application is incomplete: neither {} nor {} contains public/index.php",
            bundled_app.display(),
            repo_root.display()
        ));
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
        data_dir: paths::simplify(&data_dir),
        app_root: paths::simplify(&app_root),
        runtime_dir: paths::simplify(&runtime_dir),
        php_ini: paths::simplify(&php_ini),
        postgres_bundle: paths::simplify(&postgres_bundle),
    })
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    let builder = tauri::Builder::default()
        .plugin(tauri_plugin_opener::init())
        // Auto-update: check a signed manifest, then let the NSIS installer run
        // in passive mode and relaunch. `setup_stop_server` (below) releases the
        // database and HTTP runtime first, so the installer can replace files.
        .plugin(tauri_plugin_updater::Builder::new().build())
        .plugin(tauri_plugin_process::init());

    // Test-only. `--features e2e` embeds a W3C WebDriver server and the
    // WebdriverIO execute/mock command surface so `@wdio/tauri-service` can
    // drive this binary (`desktop/e2e/wdio.conf.ts`). Neither plugin is in
    // `default`, and the release build must never carry a WebDriver server,
    // so this stays behind the feature gate (see Task 2 of the production
    // walkthrough plan).
    #[cfg(feature = "e2e")]
    let builder = builder
        .plugin(tauri_plugin_wdio_webdriver::init())
        .plugin(tauri_plugin_wdio::init());

    builder
        .manage(Arc::new(SetupHandle::default()))
        .setup(|app| {
            // The server role serves the LAN, so the app must be able to keep
            // running with its window closed: create a tray icon (Task 7).
            let quit = MenuItemBuilder::with_id("quit", "Quit").build(app)?;
            let menu = MenuBuilder::new(app).item(&quit).build()?;

            if let Some(icon) = app.default_window_icon() {
                TrayIconBuilder::with_id("main")
                    .tooltip("MooreLife")
                    .icon(icon.clone())
                    .menu(&menu)
                    .on_tray_icon_event(|tray, event| {
                        if let TrayIconEvent::DoubleClick { .. } = event {
                            if let Some(window) = tray.app_handle().get_webview_window("main") {
                                let _ = window.show();
                                let _ = window.set_focus();
                            }
                        }
                    })
                    .build(app)?;
            }

            Ok(())
        })
        .on_window_event(|window, event| {
            // While a server is booted, closing the window must not kill the
            // LAN service: hide to tray instead. A Quit menu item exits.
            if let WindowEvent::CloseRequested { api, .. } = event {
                let state = window.state::<Arc<SetupHandle>>();
                if server_active(&state) {
                    api.prevent_close();
                    let _ = window.hide();
                }
            }
        })
        .on_menu_event(|app, event| {
            if event.id().as_ref() == "quit" {
                app.exit(0);
            }
        })
        .invoke_handler(tauri::generate_handler![
            greet,
            setup_plan,
            setup_server_active,
            setup_status,
            setup_run_server,
            setup_run_terminal,
            setup_run_staff_client,
            setup_stop_server,
            discovery_probe,
            discovery_pairing,
            updates_enabled
        ])
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}
