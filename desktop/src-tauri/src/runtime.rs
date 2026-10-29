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

use crate::paths;

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
        // PHP cannot open verbatim (`\\?\`) paths, and Windows tooling hands
        // them out readily, so both the served root and the working directory
        // are simplified before they reach the child (see `crate::paths`).
        let app_root = paths::simplify(app_root);
        let public = app_root.join("public");

        let stdout = OpenOptions::new()
            .create(true)
            .append(true)
            .open(log_path)
            .with_context(|| format!("failed to open log {}", log_path.display()))?;
        let stderr = stdout.try_clone().context("failed to clone log handle")?;

        let mut command = Command::new(paths::simplify(binary));
        command
            .arg("php-server")
            .arg(format!("--listen={host}:{port}"))
            .arg(format!("--root={}", public.display()))
            .current_dir(&app_root)
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
    ///
    /// The check is deliberately strict. A booted process is not a working
    /// server: if PHP cannot open the entry script it still answers HTTP with a
    /// fatal error body, and treating that as "ready" would hand the operator a
    /// broken browser tab. A child that dies (for example because another
    /// process already owns the port) fails immediately instead of waiting for
    /// the full timeout.
    pub fn wait_until_ready(&mut self, timeout: Duration) -> Result<()> {
        let deadline = Instant::now() + timeout;
        let url = format!("http://127.0.0.1:{}/up", self.port);
        let client = reqwest::blocking::Client::builder()
            .timeout(Duration::from_secs(2))
            .build()
            .context("failed to build health-check client")?;

        while Instant::now() < deadline {
            if !self.is_running() {
                bail!(
                    "FrankenPHP exited before it began serving on port {}; see {}\n{}",
                    self.port,
                    self.log_path.display(),
                    self.tail_log(20)
                );
            }

            if let Ok(response) = client.get(&url).send() {
                let status = response.status();
                let body = response.text().unwrap_or_default();

                if let Some(cause) = php_boot_failure(&body) {
                    bail!(
                        "PHP cannot open the application entry script: {cause}\nsee {}",
                        self.log_path.display()
                    );
                }

                if status.is_success() {
                    return Ok(());
                }
            }

            std::thread::sleep(Duration::from_millis(250));
        }

        bail!(
            "FrankenPHP did not become ready within {timeout:?}; see {}\n{}",
            self.log_path.display(),
            self.tail_log(20)
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

/// Detect PHP's "the entry script could not be opened" failure inside a
/// readiness response.
///
/// FrankenPHP answers HTTP even when PHP cannot open `public/index.php`, so the
/// status code alone is not evidence that the application is serving. The
/// returned string is the most specific line from the body, suitable for the
/// step message the wizard shows.
fn php_boot_failure(body: &str) -> Option<String> {
    if !body.contains("Failed opening required") && !body.contains("Fatal error") {
        return None;
    }

    let plain = body
        .replace("<br />", "\n")
        .replace("<br>", "\n")
        .replace("<b>", "")
        .replace("</b>", "");

    let detail = plain
        .lines()
        .map(str::trim)
        .find(|line| line.contains("Failed opening required"))
        .or_else(|| {
            plain
                .lines()
                .map(str::trim)
                .find(|line| line.contains("Fatal error"))
        });

    Some(detail.unwrap_or("PHP reported a fatal error").to_string())
}

#[cfg(test)]
mod tests {
    use super::{php_boot_failure, Runtime};
    use std::fs;
    use std::net::TcpListener;
    use std::path::{Path, PathBuf};
    use std::time::{Duration, SystemTime, UNIX_EPOCH};

    #[test]
    fn a_healthy_readiness_page_is_not_a_failure() {
        let body = "<!DOCTYPE html><html><head><title>Laravel</title></head><body>Application up</body></html>";
        assert_eq!(php_boot_failure(body), None);
    }
    #[test]
    fn a_verbatim_root_failure_is_reported_with_the_offending_path() {
        // Captured from FrankenPHP 1.13/PHP 8.5 when `--root` carried `\\?\`.
        let body = "<br />\n<b>Warning</b>:  Unknown: Failed to open stream: No such file or directory in <b>Unknown</b> on line <b>0</b><br />\n<br />\n<b>Fatal error</b>:  Failed opening required '\\\\?\\C:\\app\\public\\index.php' (include_path='.;C:\\php\\pear') in <b>Unknown</b> on line <b>0</b><br />";
        let cause = php_boot_failure(body).expect("the fatal must be detected");
        assert!(cause.contains("Failed opening required"));
        assert!(cause.contains("index.php"));
    }

    #[test]
    fn any_fatal_error_still_fails_the_readiness_check() {
        let body = "<b>Fatal error</b>: Uncaught Error: boom";
        let cause = php_boot_failure(body).expect("a fatal must be detected");
        assert!(cause.contains("Fatal error"));
    }

    /// The regression this guards: Windows tooling can hand the runtime a
    /// verbatim (`\\?\`) application root, and PHP cannot open `index.php`
    /// through that prefix. The server must simplify the path it passes on.
    ///
    /// Runs the bundled FrankenPHP against a throwaway app when the binary is
    /// available, and is a no-op on machines without it.
    #[test]
    fn a_verbatim_application_root_is_served() {
        let Some(binary) = bundled_frankenphp() else {
            return;
        };

        let dir = temp_dir("verbatim-root");
        let app_root = dir.join("app");
        fs::create_dir_all(app_root.join("public")).unwrap();
        fs::write(app_root.join("public").join("index.php"), "<?php echo 'ready';").unwrap();

        let verbatim = PathBuf::from(format!(r"\\?\{}", app_root.display()));
        assert!(verbatim.exists(), "the verbatim path must resolve on disk");

        let port = free_port();
        let log = dir.join("frankenphp.log");
        let mut runtime = Runtime::start(
            &verbatim,
            &binary,
            "127.0.0.1",
            port,
            &[],
            &log,
        )
        .expect("the runtime starts with a verbatim root");

        runtime
            .wait_until_ready(Duration::from_secs(30))
            .expect("PHP must be able to open the entry script");

        let _ = fs::remove_dir_all(&dir);
    }

    fn bundled_frankenphp() -> Option<PathBuf> {
        let manifest = Path::new(env!("CARGO_MANIFEST_DIR"));
        let repo_root = manifest.join("..").join("..");
        let name = if cfg!(windows) {
            "frankenphp.exe"
        } else {
            "frankenphp"
        };
        let candidates = [
            repo_root.join(format!("resources/bin/{}/{}", host_triple(), name)),
            manifest.join("target").join("release").join("runtime").join(name),
        ];

        candidates.into_iter().find(|path| path.exists())
    }

    fn host_triple() -> &'static str {
        if cfg!(target_os = "windows") {
            "x86_64-pc-windows-msvc"
        } else {
            "host"
        }
    }

    fn free_port() -> u16 {
        TcpListener::bind("127.0.0.1:0")
            .expect("a loopback port")
            .local_addr()
            .unwrap()
            .port()
    }

    fn temp_dir(tag: &str) -> PathBuf {
        let dir = std::env::temp_dir().join(format!(
            "hotelms-runtime-{tag}-{}-{}",
            std::process::id(),
            SystemTime::now()
                .duration_since(UNIX_EPOCH)
                .unwrap()
                .as_nanos()
        ));
        let _ = fs::remove_dir_all(&dir);
        fs::create_dir_all(&dir).unwrap();
        dir
    }
}
