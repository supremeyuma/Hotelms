//! Runs Artisan through the PHP binary that ships inside the FrankenPHP archive.
//!
//! The bulk of setup — migrations, seeding, creating the administrator — is CLI
//! work, not HTTP traffic, so it must not go through the long-running server.
//!
//! Two facts, verified against the bundled build (PHP 8.5.11 ZTS), shape this:
//!
//!   * `frankenphp.exe php-cli` is unavailable below PHP 8.6, so `artisan` runs
//!     through the archive's own `php.exe` (Windows) or `php` (macOS/Linux).
//!   * The archive's `php.ini-production` leaves every shared extension
//!     commented out, so a bare invocation reports `pdo_pgsql` and `gd` missing
//!     even though the DLLs are present. The curated `php.ini` is placed beside
//!     the binary and `PHPRC` points at that directory, which is exactly what
//!     `scripts/verify-runtime.ps1` checks.

use std::fs;
use std::path::{Path, PathBuf};
use std::process::{Command, Output, Stdio};

use anyhow::{Context, Result, bail};

/// The bundled PHP CLI, pinned to a runtime directory and its configuration.
pub struct PhpCli {
    php: PathBuf,
    runtime_dir: PathBuf,
}

impl PhpCli {
    /// Resolve the PHP binary under `runtime_dir` and make sure the curated
    /// `php_ini` is in place beside it so `extension_dir = "ext"` resolves.
    pub fn new(runtime_dir: &Path, php_ini: &Path) -> Result<Self> {
        // PHP's CLI has the same trouble with verbatim Windows paths as the
        // server does, so the runtime directory is simplified once here and
        // every path this struct hands to the child is derived from it.
        let runtime_dir = crate::paths::simplify(runtime_dir);
        let php = if cfg!(windows) {
            runtime_dir.join("php.exe")
        } else {
            runtime_dir.join("php")
        };

        if !php.exists() {
            bail!("bundled PHP binary missing at {}", php.display());
        }

        let curated = fs::read(php_ini)
            .with_context(|| format!("failed to read curated php.ini at {}", php_ini.display()))?;

        let target = runtime_dir.join("php.ini");
        let already_current = fs::read(&target).ok().as_deref() == Some(curated.as_slice());
        if !already_current {
            fs::write(&target, &curated)
                .with_context(|| format!("failed to place php.ini at {}", target.display()))?;
        }

        Ok(Self { php, runtime_dir })
    }

    /// Run `php artisan <args>` in `app_root`, returning the process output.
    ///
    /// `env` carries the role's database and sync settings as process
    /// environment. Laravel's Dotenv is immutable, so process environment wins
    /// over any `.env` in the application tree — the setup never has to write
    /// into read-only bundled resources.
    pub fn artisan(
        &self,
        app_root: &Path,
        args: &[&str],
        env: &[(String, String)],
    ) -> Result<Output> {
        let mut command = Command::new(&self.php);
        command
            .arg("artisan")
            .args(args)
            .current_dir(app_root)
            .env("PHPRC", &self.runtime_dir)
            .env("APP_ENV", "production")
            .env("APP_DEBUG", "false")
            .stdin(Stdio::null());

        for (key, value) in env {
            command.env(key, value);
        }

        let output = command
            .output()
            .with_context(|| format!("failed to run `artisan {}`", args.join(" ")))?;

        if !output.status.success() {
            bail!(
                "`artisan {}` failed ({})\nstdout: {}\nstderr: {}",
                args.join(" "),
                output.status,
                String::from_utf8_lossy(&output.stdout).trim(),
                String::from_utf8_lossy(&output.stderr).trim(),
            );
        }

        Ok(output)
    }
}
