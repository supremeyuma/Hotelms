//! The re-runnable setup engine behind the wizard (Phase 6, Task 5).
//!
//! Spec 9.2 defines one installer with three shapes, chosen explicitly by the
//! first question. This module owns the *orchestration* — the ordered, reported,
//! resumable steps — and leaves the screen that drives it to the UI slice.
//!
//! Design rules, all of them learning from the plan and spec:
//!
//!   * **Every step is reported.** The caller receives a `StepEvent` before and
//!     after each step, so a failure names the step and the cause rather than
//!     leaving a blank window.
//!   * **Resources that must be live are always re-established.** Postgres, the
//!     `.env` and the HTTP runtime are started unconditionally; only the durable
//!     one-shot mutations (seed, admin creation) consult the resume record, so
//!     an interrupted wizard does not redo work that would duplicate rows.
//!   * **Nothing writes into the application tree.** The `.env` lives in the
//!     data directory and is *also* passed as process environment, because
//!     Laravel's Dotenv is immutable. Bundled resources stay read-only.

pub mod phpcli;
pub mod state;

use std::fs;
use std::path::{Path, PathBuf};
use std::process::Command;
use std::time::Duration;

use anyhow::{Context, Result, bail};
use serde::Serialize;

use crate::envfile::{self, Role};
use crate::postgres::EmbeddedPostgres;
use crate::runtime::Runtime;

use phpcli::PhpCli;
use state::SetupState;

/// The HTTP port a role serves on by default.
pub const DEFAULT_HTTP_PORT: u16 = 8000;
/// The embedded Postgres port for the server role.
pub const DEFAULT_DB_PORT: u16 = 5432;
/// The server role binds every interface so LAN peers can reach it (spec 9.2).
pub const LISTEN_ALL: &str = "0.0.0.0";

// Step ids. Stable strings, because they are persisted in the resume record and
// keyed against by the UI.
pub const STEP_UNPACK_POSTGRES: &str = "unpack_postgres";
pub const STEP_GENERATE_ENV: &str = "generate_env";
pub const STEP_MIGRATE: &str = "migrate";
pub const STEP_SEED: &str = "seed";
pub const STEP_CREATE_ADMIN: &str = "create_admin";
pub const STEP_BIND_NETWORK: &str = "bind_network";
pub const STEP_FIREWALL: &str = "register_firewall";
pub const STEP_CLOUD: &str = "register_cloud";
pub const STEP_TRAY: &str = "install_tray";
pub const STEP_CREATE_DATABASE: &str = "create_database";
pub const STEP_SNAPSHOT: &str = "pull_snapshot";
pub const STEP_REGISTER_NODE: &str = "register_node";
pub const STEP_DISCOVER: &str = "discover_server";
pub const STEP_OPEN_WINDOW: &str = "open_window";

/// Which of the three shapes an installation takes.
#[derive(Clone, Copy, Debug, PartialEq, Eq)]
pub enum SetupRole {
    Server,
    Terminal,
    StaffClient,
}

/// A single setup step, as shown to the user.
#[derive(Clone, Debug, Serialize)]
pub struct StepView {
    pub id: &'static str,
    pub title: &'static str,
}

#[derive(Clone, Debug)]
pub struct Step {
    pub id: &'static str,
    pub title: &'static str,
}

#[derive(Clone, Copy, Debug, PartialEq, Eq, Serialize)]
#[serde(rename_all = "camelCase")]
pub enum StepStatus {
    Started,
    Succeeded,
    Skipped,
    Failed,
}

/// Progress for one step. `message` carries the failure cause, a skip reason,
/// or an informational note (for example, the cloud step's no-op explanation).
#[derive(Clone, Debug, Serialize)]
#[serde(rename_all = "camelCase")]
pub struct StepEvent {
    pub id: &'static str,
    pub title: &'static str,
    pub status: StepStatus,
    pub message: Option<String>,
}

/// The ordered steps for `role`.
pub fn plan_for(role: SetupRole) -> Vec<Step> {
    match role {
        SetupRole::Server => vec![
            step(STEP_UNPACK_POSTGRES, "Unpack embedded Postgres"),
            step(STEP_GENERATE_ENV, "Generate environment"),
            step(STEP_MIGRATE, "Run database migrations"),
            step(STEP_SEED, "Seed initial data"),
            step(STEP_CREATE_ADMIN, "Create administrator account"),
            step(STEP_BIND_NETWORK, "Serve the network"),
            step(STEP_FIREWALL, "Register firewall rule"),
            step(STEP_CLOUD, "Register with the cloud"),
            step(STEP_TRAY, "Install tray application"),
        ],
        SetupRole::Terminal => vec![
            step(STEP_CREATE_DATABASE, "Create local database"),
            step(STEP_MIGRATE, "Run database migrations"),
            step(STEP_SNAPSHOT, "Pull initial snapshot"),
            step(STEP_REGISTER_NODE, "Register as a node"),
        ],
        SetupRole::StaffClient => vec![
            step(STEP_DISCOVER, "Discover the server"),
            step(STEP_OPEN_WINDOW, "Open the application"),
        ],
    }
}

/// The plan as serializable views, for the wizard's first screen.
pub fn plan_views(role: SetupRole) -> Vec<StepView> {
    plan_for(role)
        .into_iter()
        .map(|s| StepView {
            id: s.id,
            title: s.title,
        })
        .collect()
}

fn step(id: &'static str, title: &'static str) -> Step {
    Step { id, title }
}

fn title_of(role: SetupRole, id: &str) -> &'static str {
    plan_for(role)
        .into_iter()
        .find(|s| s.id == id)
        .map(|s| s.title)
        // Every emitted id is a plan member; this is only a safety net.
        .unwrap_or("Setup step")
}

/// Where everything lives. Supplied by the caller so the engine is testable
/// without a Tauri app handle.
pub struct Paths {
    pub data_dir: PathBuf,
    /// The Laravel application root (contains `artisan`, `public/`, `vendor/`).
    pub app_root: PathBuf,
    /// Directory holding the bundled runtime binaries (`php.exe`, `frankenphp.exe`, `ext/`).
    pub runtime_dir: PathBuf,
    /// The curated `php.ini` (repository `resources/runtime/php.ini`).
    pub php_ini: PathBuf,
    /// Bundled Postgres `pgsql/` directory (contains `bin/`).
    pub postgres_bundle: PathBuf,
}

/// The administrator the wizard collected on the server path.
pub struct AdminCredentials {
    pub name: String,
    pub email: String,
    pub password: String,
    pub property: Option<String>,
}

pub struct ServerOptions {
    pub http_port: u16,
    pub db_port: u16,
    pub admin: AdminCredentials,
}

/// A booted LAN server: both child processes are owned, and dropped in order.
pub struct ServerBoot {
    /// Declared first so it stops before Postgres on shutdown.
    pub runtime: Runtime,
    pub postgres: EmbeddedPostgres,
    pub env: Vec<(String, String)>,
    pub property_id: Option<i64>,
}

/// What the wizard collected on the terminal path (spec 9.2). Pairing remains
/// part of the flow; the snapshot pull and node registration that use it land
/// with discovery (Task 6).
pub struct TerminalOptions {
    pub property_code: String,
    pub pairing_code: String,
    pub http_port: u16,
}

pub struct TerminalBoot {
    pub env: Vec<(String, String)>,
}

/// A staff client needs no database; the address is all it points at.
pub struct ClientOptions {
    pub server_url: String,
}

pub struct ClientBoot {
    pub env: Vec<(String, String)>,
    pub server_url: String,
}

/// Run the server path end to end, reporting each step as it goes.
pub fn run_server(
    paths: &Paths,
    options: &ServerOptions,
    mut progress: impl FnMut(StepEvent),
) -> Result<ServerBoot> {
    let role = SetupRole::Server;
    let mut state = SetupState::load(&paths.data_dir)?;
    let mut report = |id: &'static str, status: StepStatus, message: Option<String>| {
        progress(StepEvent {
            id,
            title: title_of(role, id),
            status,
            message,
        });
    };

    // --- Unpack Postgres into the data directory, then start it (spec 9.1). ---
    report(STEP_UNPACK_POSTGRES, StepStatus::Started, None);
    let postgres = match start_embedded_postgres(paths, options.db_port) {
        Ok(pg) => {
            state.mark_complete(STEP_UNPACK_POSTGRES)?;
            report(STEP_UNPACK_POSTGRES, StepStatus::Succeeded, None);
            pg
        }
        Err(error) => {
            report(
                STEP_UNPACK_POSTGRES,
                StepStatus::Failed,
                Some(error.to_string()),
            );
            return Err(error);
        }
    };

    // --- Generate the environment. Written to the data dir *and* passed as
    // process environment, so nothing is written into the application tree. ---
    report(STEP_GENERATE_ENV, StepStatus::Started, None);
    let rendered = envfile::render(
        &Role::LanServer {
            port: options.http_port,
            db_port: options.db_port,
        },
        &paths.data_dir,
    )?;
    let env_file = paths.data_dir.join(".env");
    fs::write(&env_file, &rendered)
        .with_context(|| format!("failed to write {}", env_file.display()))?;
    let mut env = parse_env(&rendered);
    state.mark_complete(STEP_GENERATE_ENV)?;
    report(STEP_GENERATE_ENV, StepStatus::Succeeded, None);

    // --- Migrations run every time: they are idempotent, and running them
    // against a database that was wiped must not be skipped. ---
    report(STEP_MIGRATE, StepStatus::Started, None);
    let php = PhpCli::new(&paths.runtime_dir, &paths.php_ini)?;
    if let Err(error) = php.artisan(&paths.app_root, &["migrate", "--force"], &env) {
        report(STEP_MIGRATE, StepStatus::Failed, Some(error.to_string()));
        return Err(error);
    }
    state.mark_complete(STEP_MIGRATE)?;
    report(STEP_MIGRATE, StepStatus::Succeeded, None);

    // --- Seeding is not idempotent (some seeders insert rows directly), so it
    // is skipped once it has completed. ---
    if state.is_complete(STEP_SEED) {
        report(
            STEP_SEED,
            StepStatus::Skipped,
            Some("already seeded".into()),
        );
    } else {
        report(STEP_SEED, StepStatus::Started, None);
        if let Err(error) = php.artisan(&paths.app_root, &["db:seed", "--force"], &env) {
            report(STEP_SEED, StepStatus::Failed, Some(error.to_string()));
            return Err(error);
        }
        state.mark_complete(STEP_SEED)?;
        report(STEP_SEED, StepStatus::Succeeded, None);
    }

    // --- Create the administrator and bind a property. The command is
    // idempotent, but skipped once done so a resume does not re-hash. ---
    let mut property_id = None;
    if state.is_complete(STEP_CREATE_ADMIN) {
        report(
            STEP_CREATE_ADMIN,
            StepStatus::Skipped,
            Some("administrator already created".into()),
        );
    } else {
        report(STEP_CREATE_ADMIN, StepStatus::Started, None);
        match create_admin(&php, paths, options, &env) {
            Ok(id) => {
                property_id = id;
                if let Some(pid) = property_id {
                    env.push(("SYNC_PROPERTY_ID".to_string(), pid.to_string()));
                    append_env(&env_file, "SYNC_PROPERTY_ID", &pid.to_string())?;
                }
                state.mark_complete(STEP_CREATE_ADMIN)?;
                report(STEP_CREATE_ADMIN, StepStatus::Succeeded, None);
            }
            Err(error) => {
                report(
                    STEP_CREATE_ADMIN,
                    StepStatus::Failed,
                    Some(error.to_string()),
                );
                return Err(error);
            }
        }
    }

    // --- Serve the network, bound to every interface. ---
    report(STEP_BIND_NETWORK, StepStatus::Started, None);
    let frankenphp = if cfg!(windows) {
        paths.runtime_dir.join("frankenphp.exe")
    } else {
        paths.runtime_dir.join("frankenphp")
    };
    let runtime_log = paths.data_dir.join("logs").join("frankenphp.log");
    let runtime = match Runtime::start(
        &paths.app_root,
        &frankenphp,
        LISTEN_ALL,
        options.http_port,
        &env,
        &runtime_log,
    )
    .and_then(|r| {
        r.wait_until_ready(Duration::from_secs(60))?;
        Ok(r)
    }) {
        Ok(runtime) => {
            state.mark_complete(STEP_BIND_NETWORK)?;
            report(STEP_BIND_NETWORK, StepStatus::Succeeded, None);
            runtime
        }
        Err(error) => {
            report(
                STEP_BIND_NETWORK,
                StepStatus::Failed,
                Some(error.to_string()),
            );
            return Err(error);
        }
    };

    // --- Firewall rule. Failable and reported, never silent: without the rule
    // LAN peers cannot connect, but a missing elevation must not abort setup. ---
    if !cfg!(windows) {
        report(
            STEP_FIREWALL,
            StepStatus::Skipped,
            Some("firewall rules are managed only on Windows".into()),
        );
    } else if state.is_complete(STEP_FIREWALL) {
        report(
            STEP_FIREWALL,
            StepStatus::Skipped,
            Some("already registered".into()),
        );
    } else {
        report(STEP_FIREWALL, StepStatus::Started, None);
        match ensure_firewall_rule(options.http_port) {
            Ok(()) => {
                state.mark_complete(STEP_FIREWALL)?;
                report(STEP_FIREWALL, StepStatus::Succeeded, None);
            }
            Err(error) => {
                // Reported, not fatal: recorded so the UI can offer a retry.
                report(STEP_FIREWALL, StepStatus::Failed, Some(error.to_string()));
            }
        }
    }

    // --- Cloud registration: a reported no-op until Phase 8. No endpoint is
    // invented; the step is retryable and never blocks local operation. ---
    report(STEP_CLOUD, StepStatus::Started, None);
    report(
        STEP_CLOUD,
        StepStatus::Succeeded,
        Some("Cloud registration is not configured yet; retryable, and local operation is unaffected.".into()),
    );

    // --- Tray/autostart is deferred to packaging (Task 7). ---
    report(
        STEP_TRAY,
        StepStatus::Skipped,
        Some("tray/autostart is installed at packaging (Task 7)".into()),
    );

    Ok(ServerBoot {
        runtime,
        postgres,
        env,
        property_id,
    })
}

/// Run the terminal path (spec 9.2): create the local SQLite file, generate the
/// environment, and migrate. The snapshot pull and node registration follow
/// once discovery/pairing exist (Task 6); until then they are reported as
/// deferred rather than silently skipped.
pub fn run_terminal(
    paths: &Paths,
    options: &TerminalOptions,
    mut progress: impl FnMut(StepEvent),
) -> Result<TerminalBoot> {
    let role = SetupRole::Terminal;
    let mut state = SetupState::load(&paths.data_dir)?;
    let mut report = |id: &'static str, status: StepStatus, message: Option<String>| {
        progress(StepEvent {
            id,
            title: title_of(role, id),
            status,
            message,
        });
    };
    let _ = options.http_port; // reserved for the offline serving phase.

    // --- Create the local SQLite file and the role environment. ---
    report(STEP_CREATE_DATABASE, StepStatus::Started, None);
    let rendered = envfile::render(&Role::Terminal, &paths.data_dir)?;
    let db_file = paths.data_dir.join("hotelms.sqlite");
    fs::write(&db_file, "").with_context(|| format!("failed to create {}", db_file.display()))?;
    let env_file = paths.data_dir.join(".env");
    fs::write(&env_file, &rendered)
        .with_context(|| format!("failed to write {}", env_file.display()))?;
    let env = parse_env(&rendered);
    state.mark_complete(STEP_CREATE_DATABASE)?;
    report(STEP_CREATE_DATABASE, StepStatus::Succeeded, None);

    // --- Migrations against the local file. ---
    report(STEP_MIGRATE, StepStatus::Started, None);
    let php = PhpCli::new(&paths.runtime_dir, &paths.php_ini)?;
    if let Err(error) = php.artisan(&paths.app_root, &["migrate", "--force"], &env) {
        report(STEP_MIGRATE, StepStatus::Failed, Some(error.to_string()));
        return Err(error);
    }
    state.mark_complete(STEP_MIGRATE)?;
    report(STEP_MIGRATE, StepStatus::Succeeded, None);

    // --- Deferred to discovery/pairing (Task 6). ---
    report(
        STEP_SNAPSHOT,
        StepStatus::Skipped,
        Some(
            "the initial snapshot is pulled from the LAN server once discovery lands (Task 6)"
                .to_string(),
        ),
    );
    report(
        STEP_REGISTER_NODE,
        StepStatus::Skipped,
        Some(format!(
            "node registration pairs property '{}' with pairing code '{}' and lands with discovery (Task 6)",
            options.property_code, options.pairing_code
        )),
    );

    Ok(TerminalBoot { env })
}

/// Prepare a staff client: no database, no migrations — just an environment
/// that points the window at the chosen server. Auto-discovery replaces the
/// typed address in Task 6; the address given here is used until then.
pub fn run_staff_client(
    paths: &Paths,
    options: &ClientOptions,
    mut progress: impl FnMut(StepEvent),
) -> Result<ClientBoot> {
    let role = SetupRole::StaffClient;
    let mut report = |id: &'static str, status: StepStatus, message: Option<String>| {
        progress(StepEvent {
            id,
            title: title_of(role, id),
            status,
            message,
        });
    };

    report(
        STEP_DISCOVER,
        StepStatus::Skipped,
        Some("LAN auto-discovery lands in Task 6; the address you entered is used".to_string()),
    );

    let rendered = envfile::render(
        &Role::StaffClient {
            server_url: options.server_url.clone(),
        },
        &paths.data_dir,
    )?;
    let env_file = paths.data_dir.join(".env");
    fs::write(&env_file, &rendered)
        .with_context(|| format!("failed to write {}", env_file.display()))?;
    let env = parse_env(&rendered);

    report(STEP_OPEN_WINDOW, StepStatus::Started, None);
    report(
        STEP_OPEN_WINDOW,
        StepStatus::Succeeded,
        Some(format!(
            "the application window connects to {}",
            options.server_url
        )),
    );

    Ok(ClientBoot {
        env,
        server_url: options.server_url.clone(),
    })
}

/// Install (if needed) and start the embedded Postgres cluster.
fn start_embedded_postgres(paths: &Paths, db_port: u16) -> Result<EmbeddedPostgres> {
    let binaries = ensure_postgres_installed(&paths.postgres_bundle, &paths.data_dir)?;
    let data = paths.data_dir.join("pgdata");
    let log = paths.data_dir.join("logs").join("postgres.log");

    let pg = EmbeddedPostgres::start(&binaries, &data, db_port, &log)?;
    pg.ensure_database()?;
    Ok(pg)
}

/// Copy the vendored `pgsql/` tree into the data directory on first run.
///
/// Spec 9.1: Postgres is unpacked into the application data directory, not
/// installed. Returns the directory that contains `bin/`.
fn ensure_postgres_installed(bundle: &Path, data_dir: &Path) -> Result<PathBuf> {
    let target = data_dir.join("postgres");
    let server = target.join("bin").join(if cfg!(windows) {
        "postgres.exe"
    } else {
        "postgres"
    });

    if server.exists() {
        return Ok(target);
    }

    if !bundle.exists() {
        bail!(
            "bundled Postgres not found at {}; the server role cannot be set up",
            bundle.display()
        );
    }

    copy_dir(bundle, &target)?;
    Ok(target)
}

fn copy_dir(from: &Path, to: &Path) -> Result<()> {
    fs::create_dir_all(to).with_context(|| format!("failed to create {}", to.display()))?;

    for entry in fs::read_dir(from).with_context(|| format!("failed to read {}", from.display()))? {
        let entry = entry?;
        let target = to.join(entry.file_name());

        if entry.file_type()?.is_dir() {
            copy_dir(&entry.path(), &target)?;
        } else {
            fs::copy(entry.path(), &target).with_context(|| {
                format!(
                    "failed to copy {} -> {}",
                    entry.path().display(),
                    target.display()
                )
            })?;
        }
    }

    Ok(())
}

/// Run `hotelms:create-admin`, returning any bound property id.
fn create_admin(
    php: &PhpCli,
    paths: &Paths,
    options: &ServerOptions,
    env: &[(String, String)],
) -> Result<Option<i64>> {
    let report_file = paths.data_dir.join("admin.json");
    let admin = &options.admin;

    let mut args: Vec<String> = vec![
        "hotelms:create-admin".into(),
        "--name".into(),
        admin.name.clone(),
        "--email".into(),
        admin.email.clone(),
        "--password".into(),
        admin.password.clone(),
        "--output".into(),
        report_file.to_string_lossy().into_owned(),
    ];
    if let Some(property) = &admin.property {
        if !property.trim().is_empty() {
            args.push("--property".into());
            args.push(property.clone());
        }
    }

    let arg_refs: Vec<&str> = args.iter().map(String::as_str).collect();
    php.artisan(&paths.app_root, &arg_refs, env)?;

    let raw = fs::read_to_string(&report_file)
        .with_context(|| format!("expected an admin report at {}", report_file.display()))?;
    let parsed: serde_json::Value =
        serde_json::from_str(&raw).context("failed to parse the admin report")?;

    Ok(parsed
        .get("property_id")
        .and_then(serde_json::Value::as_i64))
}

/// Parse a rendered `.env` into ordered `KEY=VALUE` pairs. Blank lines and
/// comments are dropped; a value may itself contain `=`, as base64 keys do.
fn parse_env(rendered: &str) -> Vec<(String, String)> {
    rendered
        .lines()
        .map(str::trim)
        .filter(|line| !line.is_empty() && !line.starts_with('#'))
        .filter_map(|line| line.split_once('='))
        .map(|(key, value)| (key.trim().to_string(), value.trim().to_string()))
        .collect()
}

/// Append a key to a `.env` file if it is not already present.
fn append_env(path: &Path, key: &str, value: &str) -> Result<()> {
    let current =
        fs::read_to_string(path).with_context(|| format!("failed to read {}", path.display()))?;

    if current
        .lines()
        .any(|line| line.trim_start().starts_with(&format!("{key}=")))
    {
        return Ok(());
    }

    let mut updated = current;
    if !updated.ends_with('\n') {
        updated.push('\n');
    }
    updated.push_str(&format!("{key}={value}\n"));

    fs::write(path, updated).with_context(|| format!("failed to write {}", path.display()))?;
    Ok(())
}

/// Add an inbound TCP rule for the server port. Requires elevation.
fn ensure_firewall_rule(port: u16) -> Result<()> {
    #[cfg(windows)]
    {
        let name = format!("MooreLife LAN Server (TCP {port})");
        let local_port = format!("localport={port}");
        let name_arg = format!("name={name}");

        let output = Command::new("netsh")
            .args([
                "advfirewall",
                "firewall",
                "add",
                "rule",
                &name_arg,
                "dir=in",
                "action=allow",
                "protocol=TCP",
                &local_port,
            ])
            .output()
            .context("failed to run netsh")?;

        if !output.status.success() {
            bail!(
                "netsh failed ({}); run the installer as administrator: {}",
                output.status,
                String::from_utf8_lossy(&output.stderr).trim()
            );
        }

        Ok(())
    }
    #[cfg(not(windows))]
    {
        let _ = port;
        bail!("firewall rules are managed only on Windows")
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    fn temp_dir(tag: &str) -> PathBuf {
        let dir = std::env::temp_dir().join(format!(
            "hotelms-setup-{tag}-{}-{:?}",
            std::process::id(),
            std::time::SystemTime::now()
                .duration_since(std::time::UNIX_EPOCH)
                .unwrap()
                .as_nanos()
        ));
        let _ = fs::remove_dir_all(&dir);
        dir
    }

    fn env_pair<'a>(env: &'a [(String, String)], key: &str) -> Option<&'a str> {
        env.iter().find_map(|(k, v)| (k == key).then(|| v.as_str()))
    }

    #[test]
    fn server_plan_matches_the_spec_order() {
        let ids: Vec<&str> = plan_for(SetupRole::Server).iter().map(|s| s.id).collect();
        assert_eq!(
            ids,
            vec![
                STEP_UNPACK_POSTGRES,
                STEP_GENERATE_ENV,
                STEP_MIGRATE,
                STEP_SEED,
                STEP_CREATE_ADMIN,
                STEP_BIND_NETWORK,
                STEP_FIREWALL,
                STEP_CLOUD,
                STEP_TRAY,
            ]
        );
    }

    #[test]
    fn terminal_and_client_have_their_own_plans() {
        let terminal: Vec<&str> = plan_for(SetupRole::Terminal).iter().map(|s| s.id).collect();
        assert_eq!(
            terminal,
            vec![
                STEP_CREATE_DATABASE,
                STEP_MIGRATE,
                STEP_SNAPSHOT,
                STEP_REGISTER_NODE
            ]
        );

        let client: Vec<&str> = plan_for(SetupRole::StaffClient)
            .iter()
            .map(|s| s.id)
            .collect();
        assert_eq!(client, vec![STEP_DISCOVER, STEP_OPEN_WINDOW]);
    }

    #[test]
    fn plan_views_carry_titles() {
        let views = plan_views(SetupRole::Server);
        assert_eq!(views[0].id, STEP_UNPACK_POSTGRES);
        assert!(!views[0].title.is_empty());
    }

    #[test]
    fn parse_env_keeps_base64_keys_and_drops_comments() {
        let rendered = "# a comment\n\nAPP_KEY=base64:abc==\nDB_PORT=5432\n";
        let env = parse_env(rendered);

        assert_eq!(env.len(), 2);
        assert_eq!(env[0], ("APP_KEY".to_string(), "base64:abc==".to_string()));
        assert_eq!(env[1], ("DB_PORT".to_string(), "5432".to_string()));
    }

    #[test]
    fn staff_client_boot_writes_a_pointer_environment() {
        let dir = temp_dir("client");
        let paths = Paths {
            data_dir: dir.clone(),
            app_root: dir.clone(),
            runtime_dir: dir.clone(),
            php_ini: dir.join("php.ini"),
            postgres_bundle: dir.join("pg"),
        };

        let mut events = Vec::new();
        let boot = run_staff_client(
            &paths,
            &ClientOptions {
                server_url: "http://10.0.0.5:8000".into(),
            },
            |event| events.push(event),
        )
        .expect("a staff client boots without touching a database");

        assert_eq!(boot.server_url, "http://10.0.0.5:8000");
        assert_eq!(
            env_pair(&boot.env, "LAN_SERVER_URL"),
            Some("http://10.0.0.5:8000")
        );
        assert_eq!(env_pair(&boot.env, "DB_USERNAME"), None);
        assert!(dir.join(".env").exists());

        assert!(
            events
                .iter()
                .any(|e| e.id == STEP_DISCOVER && e.status == StepStatus::Skipped)
        );
        assert!(
            events
                .iter()
                .any(|e| e.id == STEP_OPEN_WINDOW && e.status == StepStatus::Succeeded)
        );
    }
}
