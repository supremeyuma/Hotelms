//! Generates the role-correct `.env` for each setup path.
//!
//! One application, three deployment shapes. Only the environment differs, so
//! this module is the single place that decides what each role points at. The
//! file is written into the application data directory, never the repository.
//!
//! `APP_KEY` and `SYNC_NODE_ID` are minted once and persisted beside the data,
//! because both must survive a restart: a changing `SYNC_NODE_ID` re-uploads the
//! whole change log and duplicates rows in every peer.

use std::fs;
use std::path::Path;

use anyhow::{bail, Context, Result};
use base64::{engine::general_purpose::STANDARD, Engine as _};
use rand::RngCore;

/// The three shapes an installation can take. Chosen by the first question the
/// wizard asks; never inferred from configuration or hostname.
pub enum Role {
    /// Owns Postgres, migrates, seeds, serves the LAN.
    LanServer { port: u16, db_port: u16 },
    /// SQLite file, no server URL, pulls a snapshot.
    Terminal,
    /// No database at all. Points at a discovered server.
    StaffClient { server_url: String },
}

/// Identity that must persist across restarts.
#[derive(Clone, Debug, PartialEq, Eq)]
pub struct Identity {
    pub app_key: String,
    pub node_id: String,
}

impl Identity {
    /// Read the identity from `data_dir/identity.env`, or mint and persist it on
    /// first run.
    pub fn load_or_create(data_dir: &Path) -> Result<Self> {
        let path = data_dir.join("identity.env");

        if path.exists() {
            let contents = fs::read_to_string(&path)
                .with_context(|| format!("failed to read {}", path.display()))?;

            let mut app_key = None;
            let mut node_id = None;

            for line in contents.lines() {
                let Some((key, value)) = line.split_once('=') else {
                    continue;
                };
                match key.trim() {
                    "APP_KEY" => app_key = Some(value.trim().to_string()),
                    "SYNC_NODE_ID" => node_id = Some(value.trim().to_string()),
                    _ => {}
                }
            }

            match (app_key, node_id) {
                (Some(app_key), Some(node_id)) if !app_key.is_empty() && !node_id.is_empty() => {
                    return Ok(Self { app_key, node_id });
                }
                _ => bail!(
                    "identity file {} is incomplete; refusing to re-mint a node id",
                    path.display()
                ),
            }
        }

        let identity = Self {
            app_key: generate_app_key(),
            node_id: ulid::Ulid::new().to_string(),
        };

        fs::create_dir_all(data_dir)
            .with_context(|| format!("failed to create data dir {}", data_dir.display()))?;
        fs::write(
            &path,
            format!(
                "APP_KEY={}\nSYNC_NODE_ID={}\n",
                identity.app_key, identity.node_id
            ),
        )
        .with_context(|| format!("failed to write {}", path.display()))?;

        Ok(identity)
    }
}

/// A Laravel-compatible `APP_KEY`: `base64:` followed by 32 random bytes, the
/// same shape `php artisan key:generate` produces for AES-256-CBC.
fn generate_app_key() -> String {
    let mut bytes = [0u8; 32];
    rand::rngs::OsRng.fill_bytes(&mut bytes);
    format!("base64:{}", STANDARD.encode(bytes))
}

/// Render the `.env` for `role`, ensuring the persisted identity exists first.
pub fn render(role: &Role, data_dir: &Path) -> Result<String> {
    let identity = Identity::load_or_create(data_dir)?;

    let base = format!(
        "APP_NAME=MooreLife\n\
         APP_ENV=production\n\
         APP_DEBUG=false\n\
         APP_KEY={}\n\
         APP_URL=http://localhost:8000\n\
         SYNC_NODE_ID={}\n\
         SYNC_BATCH_SIZE=500\n\
         SYNC_REQUEST_TIMEOUT=30\n",
        identity.app_key, identity.node_id,
    );

    let body = match role {
        Role::LanServer { port, db_port } => format!(
            "\nSYNC_ENABLED=true\n\
             DB_CONNECTION=pgsql\n\
             DB_HOST=127.0.0.1\n\
             DB_PORT={db_port}\n\
             DB_DATABASE=hotelms\n\
             DB_USERNAME=hotelms\n\
             DB_PASSWORD=hotelms\n\
             SYNC_NODE_TYPE=lan_server\n\
             SYNC_PEERS=\n\
             FRANKENPHP_PORT={port}\n\
             SESSION_DRIVER=file\n\
             CACHE_STORE=file\n\
             QUEUE_CONNECTION=sync\n"
        ),
        Role::Terminal => format!(
            "\nSYNC_ENABLED=true\n\
             DB_CONNECTION=sqlite\n\
             DB_DATABASE={}\n\
             SYNC_NODE_TYPE=terminal\n\
             SYNC_PEERS=\n\
             FRANKENPHP_PORT=8000\n",
            data_dir.join("hotelms.sqlite").display()
        ),
        // No database, no credentials: a staff client must never write database
        // settings, because it has none to write.
        Role::StaffClient { server_url } => format!(
            "\nSYNC_ENABLED=false\n\
             DB_CONNECTION=sqlite\n\
             DB_DATABASE=:memory:\n\
             SYNC_NODE_TYPE=staff_client\n\
             FRANKENPHP_PORT=8000\n\
             LAN_SERVER_URL={server_url}\n"
        ),
    };

    Ok(format!("{base}{body}"))
}

#[cfg(test)]
mod tests {
    use super::*;

    fn temp_dir(tag: &str) -> std::path::PathBuf {
        let dir = std::env::temp_dir().join(format!(
            "hotelms-env-{tag}-{}-{:?}",
            std::process::id(),
            std::time::SystemTime::now()
                .duration_since(std::time::UNIX_EPOCH)
                .unwrap()
                .as_nanos()
        ));
        let _ = fs::remove_dir_all(&dir);
        dir
    }

    fn env_value<'a>(rendered: &'a str, key: &str) -> Option<&'a str> {
        rendered
            .lines()
            .find_map(|line| line.strip_prefix(&format!("{key}=")))
    }

    #[test]
    fn lan_server_writes_pgsql_and_role_keys() {
        let dir = temp_dir("lan");
        let rendered = render(&Role::LanServer { port: 8000, db_port: 5432 }, &dir).unwrap();

        assert_eq!(env_value(&rendered, "DB_CONNECTION"), Some("pgsql"));
        assert_eq!(env_value(&rendered, "DB_PORT"), Some("5432"));
        assert_eq!(env_value(&rendered, "DB_DATABASE"), Some("hotelms"));
        assert_eq!(env_value(&rendered, "SYNC_NODE_TYPE"), Some("lan_server"));
        assert_eq!(env_value(&rendered, "FRANKENPHP_PORT"), Some("8000"));
        assert!(env_value(&rendered, "APP_KEY").unwrap().starts_with("base64:"));
    }

    #[test]
    fn terminal_uses_a_sqlite_file_and_no_server_url() {
        let dir = temp_dir("terminal");
        let rendered = render(&Role::Terminal, &dir).unwrap();

        assert_eq!(env_value(&rendered, "DB_CONNECTION"), Some("sqlite"));
        assert_eq!(env_value(&rendered, "SYNC_NODE_TYPE"), Some("terminal"));
        assert!(env_value(&rendered, "DB_DATABASE").unwrap().ends_with("hotelms.sqlite"));
        assert_eq!(env_value(&rendered, "LAN_SERVER_URL"), None);
    }

    #[test]
    fn staff_client_writes_no_database_credentials() {
        let dir = temp_dir("staff");
        let rendered = render(
            &Role::StaffClient { server_url: "http://10.0.0.2:8000".into() },
            &dir,
        )
        .unwrap();

        assert_eq!(env_value(&rendered, "SYNC_ENABLED"), Some("false"));
        assert_eq!(env_value(&rendered, "SYNC_NODE_TYPE"), Some("staff_client"));
        assert_eq!(env_value(&rendered, "LAN_SERVER_URL"), Some("http://10.0.0.2:8000"));
        assert_eq!(env_value(&rendered, "DB_USERNAME"), None);
        assert_eq!(env_value(&rendered, "DB_PASSWORD"), None);
    }

    #[test]
    fn identity_is_stable_across_renders() {
        let dir = temp_dir("identity");

        let first = Identity::load_or_create(&dir).unwrap();
        let second = Identity::load_or_create(&dir).unwrap();

        assert_eq!(first, second);
        assert!(dir.join("identity.env").exists());
    }

    #[test]
    fn render_writes_only_inside_the_data_dir() {
        let dir = temp_dir("scoped");
        let rendered = render(&Role::Terminal, &dir).unwrap();

        // The only file created is the identity beside the data directory.
        let entries: Vec<_> = fs::read_dir(&dir).unwrap().map(|e| e.unwrap().file_name()).collect();
        assert_eq!(entries.len(), 1);
        assert_eq!(entries[0], "identity.env");
        assert!(!rendered.is_empty());
    }
}
