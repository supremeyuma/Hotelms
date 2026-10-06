# Phase 6 — Tauri Application, Runtime Packaging and Setup Wizard

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Prerequisites:** [Phase 5](2026-10-05-phase-5-booking-authority-partition.md) complete, and the Ziggy plan ([Ziggy runtime route manifest](2026-10-05-ziggy-runtime-route-manifest.md)) complete — spec §9.4 calls it a prerequisite for every node other than the cloud.

**Goal:** One installer that can become a LAN server, a POS terminal, or a staff client, and supervises its own PHP runtime and database.

**Architecture:** A Tauri v2 shell with three front doors chosen by the first question asked. One bundled FrankenPHP binary serves every role; only `.env` differs. The server role additionally unpacks an embedded Postgres and supervises it as a child process. Discovery is a UDP broadcast probe, with a QR code and manual IP as fallbacks.

**Tech Stack:** Tauri v2 (Rust), FrankenPHP, PHP 8.2+, embedded PostgreSQL 16, Vue 3 + Inertia (existing front end).

**Spec:** §9.1 (bundled runtime), §9.2 (setup paths), §9.3 (discovery), §9.4 (route manifest), §12 Phase 6, §14 (risks).

**Closes:** G37, G38, G39.

---

## Read this before starting Task 2

Three decisions are not determined by the spec and each one invalidates hours of
work if guessed wrong. They are written as gates, not as steps.

### Gate A — the image extension (blocks Task 2)

Spec §9.1 requires "an image extension" among the bundled extensions but does not
name one. The candidates are `gd` and `imagick`. This matters because the
application uses Intervention Image:

```powershell
rg -n "Intervention|Intervention\\\\Image|imagecreate|Imagick" app/ composer.json
```

`gd` ships with PHP's own build and needs no external library. `imagick` requires
ImageMagick installed on the host, which is exactly the kind of system dependency
spec §9.1 says the single-binary runtime exists to avoid. On Windows, `gd` is
bundlable; `imagick` needs the ImageMagick DLL alongside the binary.

**Decision needed:** `gd` or `imagick`. This plan assumes `gd` and isolates the
choice behind one `config/image.php` check so switching is cheap. If any code path
uses an Imagick-only feature, say so before starting.

### Gate B — embedded Postgres (blocks Task 3)

Spec §9.1: "An embedded Postgres build unpacked into the application's data
directory and supervised as a child process avoids an installer entirely." It
also records the fallback: a system Postgres, which reintroduces the installer
requirement `technical layout.txt.txt:57` exists to avoid.

There is no off-the-shelf "embedded Postgres for Windows without an installer" that
is also supported on Linux and macOS. The realistic options are:

| Option | How | Cost |
|---|---|---|
| **1 — Vendored archives per platform** | Ship official Postgres zip/tar.gz binaries per target, unpack on first run, `initdb` into the app data dir, supervise `postgres` | No installer, works headless, but you own the packaging and you must keep it patched |
| **2 — `pg-embed` / `postgresql_embedded` crate** | Rust crate that downloads and manages binaries at runtime | Smallest Rust work; adds a network dependency at install time |
| **3 — System Postgres** | Require Postgres installed | Contradicts requirement 3 and `technical layout.txt.txt:57` |

Option 1 is what the spec describes and this plan assumes. Option 2 is the
pragmatic substitute if per-platform archive maintenance is unacceptable. **Confirm
which before starting Task 3.**

### Gate C — Windows FrankenPHP extensions (blocks Task 2)

Spec §9.1 lists `pdo_sqlite`, `pdo_pgsql`, `bcmath`, `intl`, `zip`. PHP
extensions are usually compiled in, but Windows DLL builds exist for several.
`pdo_pgsql` on Windows is the one most likely to be missing, and it is
non-negotiable for the server role.

**Do this check before writing any packaging code:**

```powershell
# against a downloaded FrankenPHP Windows build
.\frankenphp.exe php-cli -m
```

If `pdo_pgsql` is absent, either a custom Windows build is required (a real C
toolchain problem) or the LAN server must use a different embedded database — which
breaks the cloud-to-LAN sync topology, because the cloud is Postgres. **Surface
this finding before committing to the architecture.** If the check has not been
run, Task 2 is a spike, not an implementation plan.

### What this phase deliberately does not do

- **No tray autostart on Windows** beyond what Tauri provides natively; a
  signed installer with `startup` registration is a release concern.
- **No code signing or notarisation.** Signing requires a certificate the project
  does not have. Ship unsigned and say so in the README.
- **No updates framework.** Auto-update needs a release channel; out of scope.

---

## File Structure

**Created:**
- `desktop/package.json`, `desktop/vite.config.ts`, `desktop/index.html`
- `desktop/src-tauri/Cargo.toml`, `desktop/src-tauri/tauri.conf.json`
- `desktop/src-tauri/src/main.rs` — the supervisor
- `desktop/src-tauri/src/runtime.rs` — FrankenPHP lifecycle
- `desktop/src-tauri/src/postgres.rs` — embedded Postgres lifecycle
- `desktop/src-tauri/src/discovery.rs` — UDP probe
- `desktop/src-tauri/src/envfile.rs` — `.env` generation per role
- `desktop/src-tauri/src/setup/` — the three wizards
- `desktop/dist/` — built wizard UI
- `resources/bin/.gitkeep` — vendored runtime archives, gitignored
- `scripts/verify-runtime.ps1` — the Gate C check as a script

**Modified:**
- `bootstrap/app.php` — runtime-agnostic base path
- `.env.example` — the sync and role keys, documented

**Responsibility note:** packaging, process supervision, discovery, and the first
run wizard. Application behaviour stays in Laravel.

---

## Task 1: Scaffold the Tauri shell

**Files:**
- Create: `desktop/**` via the Tauri CLI

**Interfaces:**
- Consumes: nothing.
- Produces: a buildable shell that runs on the dev machine with no runtime attached.

- [ ] **Step 1: Check what exists**

```powershell
rg --files -g "Cargo.toml" -g "tauri.conf.json" -g "package.json" | rg -v node_modules
```

Expected: only the root `package.json`. There is no desktop application in this
repository today — no Tauri, no Electron, no Rust.

- [ ] **Step 2: Scaffold into `desktop/`**

Use `npm create tauri-app@latest` with the vanilla TypeScript template. Keep it in
`desktop/` rather than the repository root so the Laravel front end and the
desktop shell do not fight over `package.json` and `vite.config.*`.

- [ ] **Step 3: Confirm it builds and runs before adding anything**

```powershell
cd desktop
npm install
npm run tauri dev
```

Stop here and fix whatever breaks. A shell that does not launch is not a starting
point for six more files.

- [ ] **Step 4: Commit the empty shell**

Commit: `chore: scaffold the tauri desktop shell`

---

## Task 2: Bundle and supervise FrankenPHP

Blocked on Gates A and C.

**Files:**
- Create: `desktop/src-tauri/src/runtime.rs`
- Create: `scripts/verify-runtime.ps1`
- Modify: `desktop/src-tauri/tauri.conf.json`

**Interfaces:**
- Consumes: a FrankenPHP binary per target in `resources/bin/`.
- Produces: `runtime::start(profile) -> Result<Child>`, and a health check the wizard polls.

- [ ] **Step 1: Write the runtime verification script first**

```powershell
# scripts/verify-runtime.ps1
param([Parameter(Mandatory)] [string] $Binary)

$required = @('pdo_sqlite', 'pdo_pgsql', 'bcmath', 'intl', 'zip')

$modules = & $Binary php-cli -m
$missing = $required | Where-Object { $_ -notin $modules }

if ($missing) {
    Write-Error ("FrankenPHP build is missing: " + ($missing -join ', '))
    exit 1
}

Write-Host "All required extensions present."
```

This is the Gate C check, made repeatable for every target platform in CI.

- [ ] **Step 2: Verify every target before writing supervisor code**

Run `verify-runtime.ps1` against each downloaded binary. If any target lacks
`pdo_pgsql`, stop and escalate. Do not paper over it with a fallback database.

- [ ] **Step 3: Confirm the image extension decision**

```powershell
rg -n "Intervention|driver\(" app/
rg -n '"intervention/image"' composer.json composer.lock
```

Record the outcome in `desktop/README.md` alongside the Gate A decision, so the
next person does not re-derive it.

- [ ] **Step 4: Write the supervisor**

```rust
// desktop/src-tauri/src/runtime.rs
use std::path::PathBuf;
use std::process::{Child, Command, Stdio};

use anyhow::{Context, Result};

/// Supervises the bundled FrankenPHP process.
///
/// One binary serves every role. Only the working directory and environment
/// differ, so a terminal and a LAN server run identical application code.
pub struct Runtime {
    child: Child,
    pub port: u16,
}

impl Runtime {
    pub fn start(
        app_dir: &PathBuf,
        binary: &PathBuf,
        port: u16,
        extra_env: Vec<(String, String)>,
    ) -> Result<Self> {
        ensure!(binary.exists(), "FrankenPHP binary missing at {}", binary.display());

        let mut command = Command::new(binary);
        command
            // Worker mode gives genuine concurrency (spec 9.1), unlike
            // php -S which serves one request at a time.
            .arg("php-server")
            .arg(format!("127.0.0.1:{port}"))
            .current_dir(app_dir)
            .env("APP_ENV", "production")
            .env("APP_DEBUG", "false")
            .stdout(Stdio::piped())
            .stderr(Stdio::piped());

        for (key, value) in extra_env {
            command.env(key, value);
        }

        let child = command
            .spawn()
            .with_context(|| format!("failed to start FrankenPHP at {}", binary.display()))?;

        Ok(Self { child, port })
    }

    /// Poll until the runtime answers, so the wizard never shows a blank window.
    pub fn wait_until_ready(&self, timeout: std::time::Duration) -> Result<()> {
        let deadline = std::time::Instant::now() + timeout;
        let url = format!("http://127.0.0.1:{}/up", self.port);

        while std::time::Instant::now() < deadline {
            if reqwest::blocking::get(&url).is_ok() {
                return Ok(());
            }
            std::thread::sleep(std::time::Duration::from_millis(250));
        }

        anyhow::bail!("FrankenPHP did not become ready within {timeout:?}")
    }

    pub fn pid(&self) -> u32 {
        self.child.id()
    }
}
```

- [ ] **Step 5: Add a health endpoint**

Confirm the application exposes something cheap to poll. If `/up` does not exist,
add it — but do not add a general diagnostic endpoint that leaks version or
database information on a LAN-reachable surface.

- [ ] **Step 6: Supervise restarts**

If FrankenPHP exits unexpectedly the wizard must show it, not hang. Log stderr to
a file under the app data directory and surface the tail in the UI. Silent child
death is the single most common cause of "the app just closed".

- [ ] **Step 7: Add the binaries to the bundle**

Reference `resources/bin/<target-triple>/frankenphp[.exe]` in `tauri.conf.json`
under `bundle.resources`. Add `resources/bin/**` to `.gitignore` and keep a
`checksums.txt` so a corrupted download is detectable.

Commit: `feat: bundle and supervise the frankenphp runtime`

---

## Task 3: Embedded Postgres for the server role

Blocked on Gate B.

**Files:**
- Create: `desktop/src-tauri/src/postgres.rs`
- Create: `resources/postgres/` — vendored archives and `VERSION`

**Interfaces:**
- Consumes: a vendored Postgres archive per platform.
- Produces: `postgres::start(data_dir, port) -> Result<Child>` and a matching `.env`.

- [ ] **Step 1: Record the Gate B decision**

Write the chosen option and its reason into `desktop/README.md` before writing
code.

- [ ] **Step 2: Write the supervisor**

```rust
// desktop/src-tauri/src/postgres.rs
use std::path::{Path, PathBuf};
use std::process::{Child, Command};

use anyhow::{Context, Result, bail};

/// Supervises the embedded Postgres process for the LAN server role.
///
/// Spec 9.1: unpacked into the application data directory, no installer.
/// Falls back to a system Postgres when embedded is disabled, which
/// reintroduces the installer requirement -- so it is opt-in, not default.
pub struct EmbeddedPostgres {
    child: Child,
}

impl EmbeddedPostgres {
    pub fn start(
        data_dir: &Path,
        binaries: &Path,
        port: u16,
    ) -> Result<Self> {
        let pg_ctl = if cfg!(windows) {
            binaries.join("bin").join("pg_ctl.exe")
        } else {
            binaries.join("bin").join("pg_ctl")
        };

        if ! pg_ctl.exists() {
            bail!("pg_ctl missing at {}", pg_ctl.display());
        }

        // initdb only on first run. Re-running it destroys the database.
        let version_file = data_dir.join("PG_VERSION");

        if ! version_file.exists() {
            let status = Command::new(&pg_ctl)
                .current_dir(data_dir)
                .args(["initdb", "-D", data_dir.to_str().unwrap(), "-U", "hotelms", "-E", "UTF8", "--locale=C"])
                .status()
                .context("initdb failed")?;

            if ! status.success() {
                bail!("initdb exited with {status}");
            }
        }

        let child = Command::new(&pg_ctl)
            .current_dir(data_dir)
            .args([
                "-D", data_dir.to_str().unwrap(),
                "-o", &format!("-p {port} -c listen_addresses=127.0.0.1"),
                "-l", data_dir.join("postgres.log").to_str().unwrap(),
                "start",
            ])
            .status()
            .context("pg_ctl start failed")?;

        Ok(Self { child })
    }

    /// Create the application role and database if they do not exist.
    pub fn ensure_database(&self, data_dir: &Path) -> Result<()> {
        let sql = "DO $$ BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'hotelms') THEN
        CREATE ROLE hotelms LOGIN PASSWORD 'hotelms';
    END IF;
END $$;";
        // createdb / psql invoked against the local socket
        let _ = data_dir;
        let _ = sql;
        todo!("run psql against the unix socket / named pipe to create the role and database");
    }
}
```

The `ensure_database` body is left as `todo!` deliberately. It differs per
platform (unix socket versus Windows named pipe) and needs a real decision, not a
guess written from a laptop. Implement it against the actual vendored build.

- [ ] **Step 3: Pin the data directory**

Everything mutable — Postgres data, SQLite files, logs, `.env` — lives under the
platform application data directory, never inside the install directory. An
installer directory is not writable on macOS and is replaced on every update.

- [ ] **Step 4: Test a full server-role boot**

`initdb` → start → create role and database → run migrations → seed → create the
admin account. Then kill the process, restart, and confirm the data survived.

Commit: `feat: supervise an embedded postgres for the lan server role`

---

## Task 4: Generate `.env` per role

**Files:**
- Create: `desktop/src-tauri/src/envfile.rs`
- Modify: `.env.example`

**Interfaces:**
- Consumes: the chosen role.
- Produces: a `.env` in the app data directory that is correct for that role.

- [ ] **Step 1: Write the three role templates**

```rust
// desktop/src-tauri/src/envfile.rs
pub enum Role {
    /// Owns Postgres, migrates, seeds, serves the LAN.
    LanServer { port: u16, db_port: u16 },
    /// SQLite file, no server URL, pulls a snapshot.
    Terminal,
    /// No database at all. Points at a discovered server.
    StaffClient { server_url: String },
}

pub fn render(role: &Role, data_dir: &Path) -> String {
    let base = format!(
        "APP_ENV=production\nAPP_DEBUG=false\nAPP_URL=http://localhost:8000\n\
         SYNC_ENABLED=true\n\
         SYNC_BATCH_SIZE=500\n\
         SYNC_REQUEST_TIMEOUT=30\n"
    );

    match role {
        Role::LanServer { port, db_port } => format!(
            "{base}\nDB_CONNECTION=pgsql\n\
             DB_HOST=127.0.0.1\nDB_PORT={db_port}\nDB_DATABASE=hotelms\n\
             DB_USERNAME=hotelms\nDB_PASSWORD=hotelms\n\
             SYNC_NODE_TYPE=lan_server\nSYNC_PEERS=\n\
             FRANKENPHP_PORT={port}\n"
        ),
        Role::Terminal => format!(
            "{base}\nDB_CONNECTION=sqlite\nDB_DATABASE={}\n\
             SYNC_NODE_TYPE=terminal\nSYNC_PEERS=\n\
             FRANKENPHP_PORT=8000\n",
            data_dir.join("hotelms.sqlite").display()
        ),
        Role::StaffClient { server_url } => format!(
            "{base}\nDB_CONNECTION=sqlite\nDB_DATABASE=:memory:\n\
             SYNC_ENABLED=false\nSYNC_NODE_TYPE=staff_client\n\
             FRANKENPHP_PORT=8000\nLAN_SERVER_URL={server_url}\n"
        ),
    }
}
```

- [ ] **Step 2: Check the config keys actually exist**

```powershell
rg -n "'node_type'|'peers'|'batch_size'|'enabled'|'node_id'" config/sync.php
```

Add whatever is missing. Phase 4R established `config('sync.peers')`,
`sync.enabled`, `sync.node_id`, `sync.node_type`, `sync.batch_size`,
`sync.request_timeout`. If a key is absent, add it with a default that preserves
today's behaviour when unset.

- [ ] **Step 3: Generate a random app key and node id**

Never ship a fixed `APP_KEY`. Use `Str::random(64)` base64-encoded, and
`Str::ulid()->toBase32()` for `SYNC_NODE_ID`, persisted in the data directory so
restarts keep the same identity. A node whose id changes on every restart
re-uploads its whole change log and duplicates rows in every peer.

- [ ] **Step 4: Never write `.env` in the repository**

The wizard writes into the application data directory only. Add a guard test:

```php
public function test_the_installer_never_writes_to_the_repository_env(): void
{
    $this->assertFalse(file_exists(base_path('.env.local')));
}
```

Commit: `feat: generate a role-correct env file per setup path`

---

## Task 5: The setup wizard

**Files:**
- Create: `desktop/src-tauri/src/setup/**`
- Create: `desktop/dist/**`

**Interfaces:**
- Consumes: Task 4's `Role`.
- Produces: a first screen that asks one question and then branches.

- [ ] **Step 1: Build the first screen**

Spec §9.2: "One installer. Each role is chosen by the first question asked."

Three choices, each with a one-line explanation of what it means operationally:

- **Set up this PC as server** — "Runs the database and serves other devices on your network."
- **Set up as POS terminal** — "Works offline and syncs with your server."
- **Connect to a server** — "No installation; just sign in."

No role may be inferred from configuration or hostname. An explicit answer is the
only thing that decides what this machine becomes.

- [ ] **Step 2: Server path (spec §9.2)**

In order: unpack Postgres into the app data directory → generate `.env` → run
`migrate --force` → seed → create the administrator account → bind `0.0.0.0` →
register the firewall rule → register with the cloud using a pairing code →
install as a tray application that starts on login.

Every step is reported. A wizard that fails silently at "registering firewall rule"
is worse than one that says which step failed and why.

Run migrations through the supervisor, not a second PHP process:

```rust
let mut migrate = Command::new(frankenphp);
migrate.arg("php-cli").arg("artisan").arg("migrate").arg("--force");
```

- [ ] **Step 3: Terminal path (spec §9.2)**

Requests a property code and pairing code → creates the local SQLite file → runs
migrations → pulls the initial snapshot → registers as a node. **No server URL is
requested.** That is the whole point: a terminal must be installable without
knowing where the server is.

- [ ] **Step 4: Staff client path (spec §9.2)**

Discovers the server on the LAN, opens a window pointed at it, staff log in
normally. No database, no migrations, nothing to break. This role must never write
`.env` database credentials, because it has no database.

- [ ] **Step 5: Make every step re-runnable**

A wizard interrupted at step 6 must resume at step 6, not restart. Persist step
completion in the data directory. Setup runs on unreliable power in hotels.

- [ ] **Step 6: Show what is happening**

Per project UX standards: a visible progress indicator per step, a clear failure
message naming the step and the cause, and no double-submission on the continue
button. Setup is where staff meet the product for the first time.

Commit: `feat: build the three-path setup wizard`

---

## Task 6: Discovery and pairing

**Files:**
- Create: `desktop/src-tauri/src/discovery.rs`

**Interfaces:**
- Consumes: a fixed UDP port.
- Produces: `discovery::probe(timeout) -> Vec<DiscoveredServer>`.

- [ ] **Step 1: UDP broadcast probe**

Spec §9.3: a UDP broadcast probe on a fixed port, chosen over mDNS because mDNS is
unreliable on Windows networks and often blocked by group policy.

```rust
// desktop/src-tauri/src/discovery.rs
use std::net::{SocketAddr, UdpSocket};
use std::time::Duration;

pub struct DiscoveredServer {
    pub address: SocketAddr,
    pub name: String,
    pub version: String,
}

pub const PROBE_PORT: u16 = 48211;

/// Broadcast a fixed payload and collect replies.
///
/// The payload is a constant, not a secret. Anyone on the LAN can learn that a
/// server exists; the pairing credential is what actually authorises access.
pub fn probe(timeout: Duration) -> anyhow::Result<Vec<DiscoveredServer>> {
    let socket = UdpSocket::bind("0.0.0.0:0")?;
    socket.set_broadcast(true)?;
    socket.set_read_timeout(Some(timeout))?;

    socket.broadcast_to(b"HOTELMS_DISCOVER_V1", ([255, 255, 255, 255], PROBE_PORT).into())?;

    let mut found = Vec::new();
    let deadline = std::time::Instant::now() + timeout;

    while std::time::Instant::now() < deadline {
        let mut buf = [0u8; 512];

        match socket.recv_from(&mut buf) {
            Ok((_, from)) => {
                if let Some(server) = parse_probe(&buf) {
                    found.push(server);
                }
            }
            Err(e) if e.kind() == std::io::ErrorKind::WouldBlock
                || e.kind() == std::io::ErrorKind::TimedOut => break,
            Err(e) => return Err(e.into()),
        }
    }

    Ok(found)
}
```

- [ ] **Step 2: Respond to probes from the server role**

The server role listens on `PROBE_PORT` and replies with its name, version and
HTTP port. Bind the listener explicitly; some Windows configurations drop
inbound UDP by default.

- [ ] **Step 3: Provide the fallbacks**

Spec §9.3 names two: a pairing QR code, and manual IP entry. Both are required,
not optional — broadcast is blocked often enough that discovery alone is not a
setup path. Render the QR code in the server wizard, and accept a typed IP in the
terminal and staff-client wizards.

- [ ] **Step 4: Test on a real network**

Two machines on the same subnet, firewall on, with the server answering. Test at
least one case where the client and server are on different subnets and
broadcast does not cross, and confirm the manual path works there.

Commit: `feat: add lan discovery with qr and manual fallbacks`

---

## Task 7: Packaging

**Files:**
- Modify: `desktop/src-tauri/tauri.conf.json`
- Create: `.github/workflows/desktop-build.yml`

- [ ] **Step 1: Configure per-target bundles**

Windows `.msi` and `.exe`, macOS `.dmg` and `.app`, Linux `.deb` and `.AppImage`.
Each target's runtime archive is a bundle resource.

- [ ] **Step 2: Verify each artefact on a clean machine**

An installer that has only been tested on the build machine proves nothing.
Install on a clean VM with no PHP, no Postgres, and no .NET, then run each of the
three roles.

- [ ] **Step 3: Add CI**

Build per platform. Run `scripts/verify-runtime.ps1` against each FrankenPHP
binary so a missing extension fails the build rather than a customer's first run.

- [ ] **Step 4: Document the unsigned state**

No code signing certificate is available. Record in `desktop/README.md` that
Windows SmartScreen and Gatekeeper will warn, and why.

Commit: `build: package the desktop application for all target platforms`

---

## Exit criteria

- The shell builds and launches on the dev machine.
- `scripts/verify-runtime.ps1` passes for every target, including `pdo_pgsql`.
- The Gate A and Gate B decisions are recorded in `desktop/README.md`.
- One installer serves all three roles, chosen by an explicit first question.
- `.env` is generated per role into the app data directory, with a random app key
  and a persistent node id.
- A terminal can be set up without knowing a server URL.
- The staff client creates no database.
- Discovery works on a real LAN, with QR and manual fallbacks.
- Each artefact is verified on a clean machine.

Next: [Phase 7 — offline terminal](2026-10-05-phase-7-offline-terminal.md).
