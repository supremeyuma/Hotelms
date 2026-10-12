//! Supervises the embedded Postgres process for the LAN-server role.
//!
//! Spec 9.1: unpacked into the application data directory, no installer.
//!
//! Unlike the plan's sketch, this runs `postgres[.exe]` directly rather than
//! `pg_ctl start`. `pg_ctl start` daemonises and exits, so the returned child
//! handle is not the server — it cannot be wait()ed on, restarted, or killed.
//! Holding the real server process is what makes supervision honest.
//!
//! First run:
//!   initdb -D <data> -U hotelms -E UTF8 --locale=C \
//!          --auth-host=scram-sha-256 --auth-local=scram-sha-256 --pwfile=<tmp>
//! Then:
//!   postgres -D <data> -p <port> -c listen_addresses=127.0.0.1

use std::fs::{self, File, OpenOptions};
use std::io::{Read, Seek, SeekFrom, Write};
use std::path::{Path, PathBuf};
use std::process::{Child, Command, Output, Stdio};
use std::time::{Duration, Instant};

use anyhow::{bail, Context, Result};

/// The local database is loopback-only and bootstrapped by the installer, so
/// its credentials are a local convenance, not a shared secret. They must match
/// the `.env` the server wizard writes (`envfile::Role::LanServer`).
pub const LOCAL_DB_USER: &str = "hotelms";
pub const LOCAL_DB_PASSWORD: &str = "hotelms";
pub const LOCAL_DB_NAME: &str = "hotelms";

/// A running embedded Postgres server.
pub struct EmbeddedPostgres {
    child: Child,
    binaries: PathBuf,
    data_dir: PathBuf,
    pub port: u16,
    log_path: PathBuf,
}

impl EmbeddedPostgres {
    /// Ensure the cluster exists, start the server, and wait until it accepts
    /// connections. `binaries` is the directory containing `bin/` (the
    /// `pgsql/` folder of a vendored archive).
    pub fn start(
        binaries: &Path,
        data_dir: &Path,
        port: u16,
        log_path: &Path,
    ) -> Result<Self> {
        let postgres = Self::exe(binaries, "postgres");
        if !postgres.exists() {
            bail!("postgres binary missing at {}", postgres.display());
        }

        fs::create_dir_all(data_dir)
            .with_context(|| format!("failed to create data dir {}", data_dir.display()))?;

        if let Some(parent) = log_path.parent() {
            fs::create_dir_all(parent)
                .with_context(|| format!("failed to create log dir {}", parent.display()))?;
        }

        // initdb only on first run. Re-running it destroys the database, so the
        // presence of PG_VERSION is the single guard.
        if !data_dir.join("PG_VERSION").exists() {
            Self::initdb(binaries, data_dir)?;
        }

        let child = Self::spawn_child(binaries, data_dir, port, log_path)?;

        let postgres = Self {
            child,
            binaries: binaries.to_path_buf(),
            data_dir: data_dir.to_path_buf(),
            port,
            log_path: log_path.to_path_buf(),
        };

        postgres.wait_until_ready(Duration::from_secs(30))?;

        Ok(postgres)
    }

    fn exe(binaries: &Path, name: &str) -> PathBuf {
        let dir = binaries.join("bin");
        if cfg!(windows) {
            dir.join(format!("{name}.exe"))
        } else {
            dir.join(name)
        }
    }

    fn initdb(binaries: &Path, data_dir: &Path) -> Result<()> {
        let initdb = Self::exe(binaries, "initdb");
        if !initdb.exists() {
            bail!("initdb binary missing at {}", initdb.display());
        }

        // initdb reads the superuser password from a file so it is never part
        // of the process command line.
        let pwfile = std::env::temp_dir().join(format!(
            "hotelms-initdb-{}.pw",
            std::process::id()
        ));
        {
            let mut file = File::create(&pwfile)
                .with_context(|| format!("failed to write {}", pwfile.display()))?;
            file.write_all(LOCAL_DB_PASSWORD.as_bytes())
                .context("failed to write initdb password file")?;
        }

        let output = Command::new(&initdb)
            .arg("-D")
            .arg(data_dir)
            .arg("-U")
            .arg(LOCAL_DB_USER)
            .arg("-E")
            .arg("UTF8")
            .arg("--locale=C")
            .arg("--auth-host=scram-sha-256")
            .arg("--auth-local=scram-sha-256")
            .arg(format!("--pwfile={}", pwfile.display()))
            .output()
            .with_context(|| format!("failed to run {}", initdb.display()));

        let _ = fs::remove_file(&pwfile);

        let output = output?;
        if !output.status.success() {
            bail!(
                "initdb failed ({}) : {}",
                output.status,
                String::from_utf8_lossy(&output.stderr).trim()
            );
        }

        Ok(())
    }

    fn spawn_child(
        binaries: &Path,
        data_dir: &Path,
        port: u16,
        log_path: &Path,
    ) -> Result<Child> {
        let postgres = Self::exe(binaries, "postgres");

        let stdout = OpenOptions::new()
            .create(true)
            .append(true)
            .open(log_path)
            .with_context(|| format!("failed to open log {}", log_path.display()))?;
        let stderr = stdout.try_clone().context("failed to clone log handle")?;

        Command::new(&postgres)
            .arg("-D")
            .arg(data_dir)
            .arg("-p")
            .arg(port.to_string())
            .arg("-c")
            .arg("listen_addresses=127.0.0.1")
            .env("PGPASSWORD", LOCAL_DB_PASSWORD)
            .stdin(Stdio::null())
            .stdout(Stdio::from(stdout))
            .stderr(Stdio::from(stderr))
            .spawn()
            .with_context(|| format!("failed to start postgres at {}", postgres.display()))
    }

    /// Poll `pg_isready` until the server accepts connections.
    pub fn wait_until_ready(&self, timeout: Duration) -> Result<()> {
        let pg_isready = Self::exe(&self.binaries, "pg_isready");
        let deadline = Instant::now() + timeout;

        while Instant::now() < deadline {
            if let Ok(output) = Command::new(&pg_isready)
                .args(["-h", "127.0.0.1", "-p", &self.port.to_string(), "-q"])
                .output()
            {
                if output.status.success() {
                    return Ok(());
                }
            }
            std::thread::sleep(Duration::from_millis(250));
        }

        bail!(
            "Postgres did not become ready within {timeout:?}; see {}",
            self.log_path.display()
        )
    }

    /// Create the application database if it does not exist. The superuser role
    /// is created by `initdb -U`, so only the database is missing on first run.
    pub fn ensure_database(&self) -> Result<()> {
        let psql = Self::exe(&self.binaries, "psql");

        let exists = self.psql_scalar(
            &psql,
            &format!(
                "SELECT 1 FROM pg_database WHERE datname = '{}'",
                LOCAL_DB_NAME
            ),
        )?;

        if exists.trim() == "1" {
            return Ok(());
        }

        self.run_psql(
            &psql,
            &format!("CREATE DATABASE {} OWNER {}", LOCAL_DB_NAME, LOCAL_DB_USER),
        )?;

        Ok(())
    }

    fn run_psql(&self, psql: &Path, sql: &str) -> Result<Output> {
        let output = Command::new(psql)
            .args([
                "-h",
                "127.0.0.1",
                "-p",
                &self.port.to_string(),
                "-U",
                LOCAL_DB_USER,
                "-d",
                "postgres",
                "-v",
                "ON_ERROR_STOP=1",
                "-c",
                sql,
            ])
            .env("PGPASSWORD", LOCAL_DB_PASSWORD)
            .output()
            .with_context(|| format!("failed to run {}", psql.display()))?;

        if !output.status.success() {
            bail!(
                "psql failed ({}) : {}",
                output.status,
                String::from_utf8_lossy(&output.stderr).trim()
            );
        }

        Ok(output)
    }

    fn psql_scalar(&self, psql: &Path, sql: &str) -> Result<String> {
        let output = Command::new(psql)
            .args([
                "-h",
                "127.0.0.1",
                "-p",
                &self.port.to_string(),
                "-U",
                LOCAL_DB_USER,
                "-d",
                "postgres",
                "-tA",
                "-c",
                sql,
            ])
            .env("PGPASSWORD", LOCAL_DB_PASSWORD)
            .output()
            .with_context(|| format!("failed to run {}", psql.display()))?;

        if !output.status.success() {
            bail!(
                "psql failed ({}) : {}",
                output.status,
                String::from_utf8_lossy(&output.stderr).trim()
            );
        }

        Ok(String::from_utf8_lossy(&output.stdout).trim().to_string())
    }

    pub fn pid(&self) -> u32 {
        self.child.id()
    }

    pub fn is_running(&mut self) -> bool {
        matches!(self.child.try_wait(), Ok(None))
    }

    pub fn data_dir(&self) -> &Path {
        &self.data_dir
    }

    pub fn log_path(&self) -> &Path {
        &self.log_path
    }

    /// Stop and start again with the same spec. The cluster persists on disk, so
    /// this is the restart path used after an unexpected exit.
    pub fn restart(&mut self) -> Result<()> {
        self.stop();
        self.child = Self::spawn_child(&self.binaries, &self.data_dir, self.port, &self.log_path)?;
        self.wait_until_ready(Duration::from_secs(30))
    }

    pub fn tail_log(&self, lines: usize) -> String {
        tail(&self.log_path, lines)
    }

    /// Stop the server. Prefers a clean shutdown via `pg_ctl stop`; falls back
    /// to killing the process if that fails.
    pub fn stop(&mut self) {
        let pg_ctl = Self::exe(&self.binaries, "pg_ctl");
        if pg_ctl.exists() {
            let _ = Command::new(&pg_ctl)
                .args(["-D", &self.data_dir.to_string_lossy(), "-m", "fast", "stop"])
                .output();
        }
        let _ = self.child.kill();
        let _ = self.child.wait();
    }
}

impl Drop for EmbeddedPostgres {
    fn drop(&mut self) {
        self.stop();
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    /// End-to-end smoke test against the vendored build. Skips (rather than
    /// fails) when the archive has not been unpacked, so CI without the runtime
    /// stays green; a developer with `resources/postgres/` present gets a real
    /// initdb → start → create → restart → persist check.
    #[test]
    fn boots_creates_and_persists_a_cluster() {
        let binaries = Path::new(env!("CARGO_MANIFEST_DIR"))
            .join("../../resources/postgres/x86_64-pc-windows-msvc/pgsql");

        if !EmbeddedPostgres::exe(&binaries, "postgres").exists() {
            eprintln!("skipping: vendored postgres not present at {}", binaries.display());
            return;
        }

        let root = std::env::temp_dir().join(format!("hotelms-pg-test-{}", std::process::id()));
        let _ = fs::remove_dir_all(&root);
        let data = root.join("data");
        let log = root.join("postgres.log");

        let mut pg = EmbeddedPostgres::start(&binaries, &data, 55432, &log)
            .expect("start embedded postgres");
        pg.ensure_database().expect("create application database");
        assert!(pg.is_running(), "server should be running");

        // Restart and confirm the cluster survived on disk.
        pg.restart().expect("restart embedded postgres");
        pg.ensure_database().expect("database still present after restart");
        assert!(data.join("PG_VERSION").exists(), "cluster data should persist");

        pg.stop();
        let _ = fs::remove_dir_all(&root);
    }
}

fn tail(path: &Path, lines: usize) -> String {
    let Ok(mut file) = File::open(path) else {
        return String::new();
    };

    let len = file.metadata().map(|m| m.len()).unwrap_or(0);
    let start = len.saturating_sub(64 * 1024);
    if file.seek(SeekFrom::Start(start)).is_err() {
        return String::new();
    }

    let mut buf = String::new();
    if file.read_to_string(&mut buf).is_err() {
        return String::new();
    }

    let all: Vec<&str> = buf.lines().collect();
    let from = all.len().saturating_sub(lines);
    all[from..].join("\n")
}
