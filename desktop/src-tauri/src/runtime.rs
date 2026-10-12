//! Supervises the bundled FrankenPHP process.
//!
//! One binary serves every role. Only the working directory and environment
//! differ, so a terminal and a LAN server run identical application code.
//!
//! The CLI surface was verified against FrankenPHP 1.13.0:
//!   frankenphp php-server --listen=<host>:<port> --root=<app>/public
//! The address is a flag, not a positional argument. `<host>` is `127.0.0.1`
//! for a private terminal and `0.0.0.0` for the LAN server role.

use std::fs::{self, File, OpenOptions};
use std::io::{Read, Seek, SeekFrom};
use std::path::{Path, PathBuf};
use std::process::{Child, Command, Stdio};
use std::time::{Duration, Instant};

use anyhow::{Context, Result, bail};

/// A running FrankenPHP child process.
///
/// Holds the launch spec so it can be restarted after an unexpected exit, and
/// captures stdout/stderr to a log file that survives restarts (append mode).
pub struct Runtime {
    child: Child,
    pub port: u16,
    host: String,
    binary: PathBuf,
    app_root: PathBuf,
    extra_env: Vec<(String, String)>,
    log_path: PathBuf,
}

impl Runtime {
    /// Start FrankenPHP serving `app_root/public` on `host:port`.
    ///
    /// `host` is `127.0.0.1` for a terminal that is not shared, and `0.0.0.0`
    /// for the LAN server role, which must be reachable from other devices.
    /// `extra_env` is applied on top of the defaults, so the caller can supply
    /// the role's `APP_KEY`, database settings and sync configuration.
    pub fn start(
        app_root: &Path,
        binary: &Path,
        host: &str,
        port: u16,
        extra_env: &[(String, String)],
        log_path: &Path,
    ) -> Result<Self> {
        if !binary.exists() {
            bail!("FrankenPHP binary missing at {}", binary.display());
        }

        if !app_root.join("public").is_dir() {
            bail!("public/ not found under {}", app_root.display());
        }

        if let Some(parent) = log_path.parent() {
            fs::create_dir_all(parent)
                .with_context(|| format!("failed to create log dir {}", parent.display()))?;
        }

        let child = Self::spawn_child(binary, app_root, host, port, extra_env, log_path)?;

        Ok(Self {
            child,
            port,
            host: host.to_string(),
            binary: binary.to_path_buf(),
            app_root: app_root.to_path_buf(),
            extra_env: extra_env.to_vec(),
            log_path: log_path.to_path_buf(),
        })
    }

    fn spawn_child(
        binary: &Path,
        app_root: &Path,
        host: &str,
        port: u16,
        extra_env: &[(String, String)],
        log_path: &Path,
    ) -> Result<Child> {
        let public = app_root.join("public");

        let stdout = OpenOptions::new()
            .create(true)
            .append(true)
            .open(log_path)
            .with_context(|| format!("failed to open log {}", log_path.display()))?;
        let stderr = stdout.try_clone().context("failed to clone log handle")?;

        let mut command = Command::new(binary);
        command
            .arg("php-server")
            .arg(format!("--listen={host}:{port}"))
            .arg(format!("--root={}", public.display()))
            .current_dir(app_root)
            .env("APP_ENV", "production")
            .env("APP_DEBUG", "false")
            .stdin(Stdio::null())
            .stdout(Stdio::from(stdout))
            .stderr(Stdio::from(stderr));

        for (key, value) in extra_env {
            command.env(key, value);
        }

        command
            .spawn()
            .with_context(|| format!("failed to start FrankenPHP at {}", binary.display()))
    }

    /// Stop the current child and launch a fresh one with the same spec.
    /// Used by the supervisor when the process exits unexpectedly.
    pub fn restart(&mut self) -> Result<()> {
        self.stop();
        self.child = Self::spawn_child(
            &self.binary,
            &self.app_root,
            &self.host,
            self.port,
            &self.extra_env,
            &self.log_path,
        )?;
        Ok(())
    }

    /// Poll `/up` until the application answers, so the wizard never shows a
    /// blank window. Returns an error naming the log on timeout.
    pub fn wait_until_ready(&self, timeout: Duration) -> Result<()> {
        let deadline = Instant::now() + timeout;
        let url = format!("http://127.0.0.1:{}/up", self.port);
        let client = reqwest::blocking::Client::builder()
            .timeout(Duration::from_secs(2))
            .build()
            .context("failed to build health-check client")?;

        while Instant::now() < deadline {
            if let Ok(response) = client.get(&url).send() {
                if response.status().is_success() {
                    return Ok(());
                }
            }
            std::thread::sleep(Duration::from_millis(250));
        }

        bail!(
            "FrankenPHP did not become ready within {timeout:?}; see {}",
            self.log_path.display()
        )
    }

    pub fn pid(&self) -> u32 {
        self.child.id()
    }

    /// True while the child process is still running. Silent child death is the
    /// most common cause of "the app just closed", so callers poll this.
    pub fn is_running(&mut self) -> bool {
        matches!(self.child.try_wait(), Ok(None))
    }

    pub fn log_path(&self) -> &Path {
        &self.log_path
    }

    /// Last `lines` lines of the runtime log, for surfacing a failure in the UI.
    pub fn tail_log(&self, lines: usize) -> String {
        let Ok(mut file) = File::open(&self.log_path) else {
            return String::new();
        };

        // Read at most the trailing 64 KiB; log files are otherwise unbounded.
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

    /// Stop the child. Best effort; called on restarts and application exit.
    pub fn stop(&mut self) {
        let _ = self.child.kill();
        let _ = self.child.wait();
    }
}

impl Drop for Runtime {
    fn drop(&mut self) {
        self.stop();
    }
}
