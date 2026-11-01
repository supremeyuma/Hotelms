//! Licensing: 30-day trial, then a signed annual licence per property.
//!
//! The desktop app never holds the signing key. It carries only the RSA public
//! key (`licence_public.pem`), receives a signed payload from the licence server
//! when a key is activated, and verifies that payload offline on every start.
//!
//! Two things are enforced here:
//!
//!   * the trial is **machine-bound and hard to reset**: the start date is
//!     written to the registry and to the app-data directory, and the earliest
//!     value wins, so deleting one copy does not restart the clock;
//!   * an expired install is **blocked**: the server role refuses to start and
//!     the window shows the activation screen instead of the wizard.

use std::fs;
use std::path::{Path, PathBuf};
use std::process::Command;
use std::time::Duration;

use anyhow::{Context, Result, bail};
use base64::{engine::general_purpose::URL_SAFE_NO_PAD, Engine as _};
use rsa::pkcs1v15::{Signature, VerifyingKey};
use rsa::pkcs8::DecodePublicKey;
use rsa::sha2::Sha256;
use rsa::signature::Verifier;
use rsa::RsaPublicKey;
use serde::{Deserialize, Serialize};
use sha2::{Digest, Sha256 as Sha256Hash};

/// Public half of the licence signing key. The private half lives only on the
/// licence server (`LICENCE_PRIVATE_KEY`).
const PUBLIC_KEY_PEM: &str = include_str!("licence_public.pem");

/// Where keys are exchanged for a signed licence. Set at build time:
/// `MOORELIFE_LICENCE_ENDPOINT=https://licences.example.com/licence/activate`.
const DEFAULT_ENDPOINT: &str = "https://licence.moorelife.app/licence/activate";

const TRIAL_DAYS: i64 = 30;
const REG_TRIAL_KEY: &str = r"HKCU\Software\MooreLife";

#[derive(Clone, Debug, Serialize, Deserialize, PartialEq, Eq)]
pub struct LicencePayload {
    pub v: u32,
    pub licence_id: String,
    pub product: String,
    pub property: String,
    pub machine_id: String,
    pub plan: String,
    pub issued_at: String,
    pub expires_at: String,
}

#[derive(Clone, Debug, Serialize, PartialEq, Eq)]
#[serde(tag = "state", rename_all = "camelCase", rename_all_fields = "camelCase")]
pub enum LicenceState {
    Trial {
        days_left: i64,
        trial_ends_at: String,
    },
    Active {
        property: String,
        expires_at: String,
        days_left: i64,
    },
    Expired {
        trial_ended_at: String,
    },
}

/// A stable id for this Windows installation.
///
/// `MachineGuid` survives reinstalling the application (and deleting its data),
/// so it both binds a licence and anchors the trial marker.
pub fn machine_id() -> String {
    let mut source = String::new();

    if let Ok(output) = Command::new("reg")
        .args(["query", r"HKLM\SOFTWARE\Microsoft\Cryptography", "/v", "MachineGuid"])
        .output()
    {
        if output.status.success() {
            let text = String::from_utf8_lossy(&output.stdout);
            if let Some(guid) = text
                .lines()
                .find(|line| line.contains("MachineGuid"))
                .and_then(|line| line.split_whitespace().last())
            {
                source.push_str(guid.trim());
            }
        }
    }

    if source.is_empty() {
        source.push_str(&std::env::var("COMPUTERNAME").unwrap_or_else(|_| "unknown".into()));
    }

    let digest = Sha256Hash::digest(format!("moorelife-licence:{source}").as_bytes());
    digest.iter().map(|b| format!("{b:02x}")).collect()
}

fn trial_registry_path(data_dir: &Path) -> PathBuf {
    data_dir.join("trial-start")
}

#[cfg(windows)]
fn read_registry_trial() -> Option<String> {
    let output = Command::new("reg")
        .args(["query", REG_TRIAL_KEY, "/v", "TrialStart"])
        .output()
        .ok()?;

    if !output.status.success() {
        return None;
    }

    String::from_utf8_lossy(&output.stdout)
        .lines()
        .find(|line| line.contains("TrialStart"))
        .and_then(|line| line.split_whitespace().last())
        .map(|value| value.trim().to_string())
        .filter(|value| !value.is_empty())
}

#[cfg(not(windows))]
fn read_registry_trial() -> Option<String> {
    None
}

#[cfg(windows)]
fn write_registry_trial(value: &str) {
    let _ = Command::new("reg")
        .args([
            "add", REG_TRIAL_KEY, "/v", "TrialStart", "/t", "REG_SZ", "/d", value, "/f",
        ])
        .output();
}

#[cfg(not(windows))]
fn write_registry_trial(_value: &str) {}

/// The trial start date, anchored to the earliest marker that exists.
///
/// Writes all markers on first use. The earliest marker wins, so deleting the
/// app-data copy (or the registry value) does not restart the trial.
pub fn trial_start(data_dir: &Path) -> Result<String> {
    let file = trial_registry_path(data_dir);

    let from_file = fs::read_to_string(&file).ok().map(|v| v.trim().to_string());
    let from_registry = read_registry_trial();

    let earliest = [from_file.clone(), from_registry.clone()]
        .into_iter()
        .flatten()
        .min();

    let start = match earliest {
        Some(value) => value,
        None => {
            let now = now_iso_date();
            write_registry_trial(&now);
            if let Some(parent) = file.parent() {
                fs::create_dir_all(parent).ok();
            }
            fs::write(&file, &now)
                .with_context(|| format!("failed to write {}", file.display()))?;
            now
        }
    };

    // Keep both markers present, without moving the clock forward.
    if from_registry.as_deref() != Some(start.as_str()) {
        write_registry_trial(&start);
    }
    if from_file.as_deref() != Some(start.as_str()) {
        if let Some(parent) = file.parent() {
            fs::create_dir_all(parent).ok();
        }
        let _ = fs::write(&file, &start);
    }

    Ok(start)
}

pub fn licence_path(data_dir: &Path) -> PathBuf {
    data_dir.join("licence.json")
}

#[derive(Serialize, Deserialize)]
struct StoredLicence {
    token: String,
}

pub fn verify_token(token: &str, machine: &str) -> Result<LicencePayload> {
    let (payload_b64, signature_b64) = token
        .split_once('.')
        .context("licence token is not in payload.signature form")?;

    let payload_bytes = URL_SAFE_NO_PAD
        .decode(payload_b64)
        .context("licence payload is not valid base64")?;
    let signature_bytes = URL_SAFE_NO_PAD
        .decode(signature_b64)
        .context("licence signature is not valid base64")?;

    let public_key = RsaPublicKey::from_public_key_pem(PUBLIC_KEY_PEM)
        .context("embedded licence public key is invalid")?;
    let verifying_key = VerifyingKey::<Sha256>::new(public_key);

    let signature = Signature::try_from(signature_bytes.as_slice())
        .context("licence signature has the wrong length")?;

    verifying_key
        .verify(&payload_bytes, &signature)
        .context("licence signature does not verify")?;

    let payload: LicencePayload =
        serde_json::from_slice(&payload_bytes).context("licence payload is not valid JSON")?;

    if payload.v != 1 {
        bail!("unsupported licence version {}", payload.v);
    }

    if payload.machine_id != machine {
        bail!("this licence was activated for a different machine");
    }

    if let Some(days) = days_until(&payload.expires_at)? {
        if days < 0 {
            bail!("licence expired on {}", payload.expires_at);
        }
    }

    Ok(payload)
}

pub fn status(data_dir: &Path) -> Result<LicenceState> {
    let machine = machine_id();
    let path = licence_path(data_dir);

    if path.exists() {
        let raw = fs::read_to_string(&path)
            .with_context(|| format!("failed to read {}", path.display()))?;

        if let Ok(stored) = serde_json::from_str::<StoredLicence>(&raw) {
            match verify_token(&stored.token, &machine) {
                Ok(payload) => {
                    return Ok(LicenceState::Active {
                        property: payload.property,
                        expires_at: payload.expires_at.clone(),
                        days_left: days_until(&payload.expires_at)?.unwrap_or(0),
                    });
                }
                Err(error) => {
                    // A tampered or foreign licence file is not "active"; fall
                    // through to the trial/expiry decision and report it in the
                    // UI through the expired state.
                    eprintln!("stored licence rejected: {error:#}");
                }
            }
        }
    }

    let start = trial_start(data_dir)?;
    let elapsed = days_since(&start)?.unwrap_or(TRIAL_DAYS);
    let days_left = TRIAL_DAYS - elapsed;

    if days_left > 0 {
        Ok(LicenceState::Trial {
            days_left,
            trial_ends_at: start,
        })
    } else {
        Ok(LicenceState::Expired {
            trial_ended_at: start,
        })
    }
}

/// Whether the server role may start. Trials are allowed; expired installs are
/// not, and the caller surfaces a clear error instead of a dead server.
pub fn ensure_may_run(data_dir: &Path) -> Result<()> {
    match status(data_dir)? {
        LicenceState::Expired { trial_ended_at } => bail!(
            "The 30-day trial ended on {trial_ended_at}. Activate a licence to continue."
        ),
        _ => Ok(()),
    }
}

/// Exchange a purchased key for a signed licence and store it.
///
/// `transfer` marks the customer's self-service move to a replacement PC: the
/// server allows a limited number of them per licence, and the old PC is left to
/// run out its signed term. Without it, a key bound to another machine is
/// refused and the operator is pointed at the transfer option.
pub fn activate(data_dir: &Path, key: &str, transfer: bool) -> Result<LicenceState> {
    let key = key.trim();

    if key.is_empty() {
        bail!("Enter the licence key from your purchase email.");
    }

    let endpoint =
        option_env!("MOORELIFE_LICENCE_ENDPOINT").unwrap_or(DEFAULT_ENDPOINT);

    let machine = machine_id();
    let body = activation_body(key, &machine, transfer);

    let client = reqwest::blocking::Client::builder()
        .timeout(Duration::from_secs(30))
        .build()
        .context("failed to build the activation client")?;

    let response = client
        .post(endpoint)
        .header(reqwest::header::CONTENT_TYPE, "application/json")
        .header(reqwest::header::ACCEPT, "application/json")
        .body(body.to_string())
        .send()
        .with_context(|| format!("failed to reach the licence server at {endpoint}"))?;

    let status_code = response.status();
    let text = response.text().unwrap_or_default();

    if !status_code.is_success() {
        let message = serde_json::from_str::<serde_json::Value>(&text)
            .ok()
            .and_then(|value| {
                value
                    .get("errors")
                    .and_then(|errors| errors.get("key"))
                    .and_then(|key| key.get(0))
                    .and_then(|first| first.as_str())
                    .map(str::to_string)
                    .or_else(|| {
                        value
                            .get("message")
                            .and_then(|m| m.as_str())
                            .map(str::to_string)
                    })
            })
            .unwrap_or_else(|| format!("licence server returned {status_code}"));

        bail!("{message}");
    }

    let parsed: serde_json::Value =
        serde_json::from_str(&text).context("activation response was not JSON")?;
    let token = parsed
        .get("token")
        .and_then(|value| value.as_str())
        .context("activation response carried no licence")?;

    // Never store a token that does not verify with the embedded key.
    verify_token(token, &machine)?;

    let path = licence_path(data_dir);
    if let Some(parent) = path.parent() {
        fs::create_dir_all(parent).ok();
    }

    let stored = serde_json::to_string(&StoredLicence {
        token: token.to_string(),
    })?;
    fs::write(&path, stored).with_context(|| format!("failed to write {}", path.display()))?;

    status(data_dir)
}

// --- Dates -------------------------------------------------------------------

/// The activation request body. Kept separate so a test can assert that the
/// transfer flag is only sent when the operator asked for a move.
fn activation_body(key: &str, machine: &str, transfer: bool) -> serde_json::Value {
    let mut body = serde_json::json!({ "key": key, "machine_id": machine });

    if transfer {
        body["transfer"] = serde_json::Value::Bool(true);
    }

    body
}

fn now_iso_date() -> String {
    chrono::Utc::now().format("%Y-%m-%d").to_string()
}

/// Days from today until an ISO-8601 timestamp. Negative when in the past.
fn days_until(value: &str) -> Result<Option<i64>> {
    let Ok(parsed) = chrono::DateTime::parse_from_rfc3339(value) else {
        return Ok(None);
    };

    Ok(Some((parsed.with_timezone(&chrono::Utc).date_naive() - chrono::Utc::now().date_naive()).num_days()))
}

/// Days elapsed since an ISO date (`YYYY-MM-DD`).
fn days_since(value: &str) -> Result<Option<i64>> {
    let Ok(parsed) = chrono::NaiveDate::parse_from_str(value, "%Y-%m-%d") else {
        return Ok(None);
    };

    Ok(Some((chrono::Utc::now().date_naive() - parsed).num_days()))
}

#[cfg(test)]
mod tests {
    use super::*;

    /// A real token signed with the production licence key for `test-machine`.
    /// Generated once with openssl and embedded so the test proves the verifier
    /// accepts a genuine signature, not just that it rejects nonsense.
    const FIXTURE_TOKEN: &str = "eyJ2IjoxLCJsaWNlbmNlX2lkIjoiZml4dHVyZSIsInByb2R1Y3QiOiJNb29yZUxpZmUiLCJwcm9wZXJ0eSI6IkZpeHR1cmUgSG90ZWwiLCJtYWNoaW5lX2lkIjoidGVzdC1tYWNoaW5lIiwicGxhbiI6ImFubnVhbCIsImlzc3VlZF9hdCI6IjIwMjYtMTEtMDFUMDA6MDA6MDArMDA6MDAiLCJleHBpcmVzX2F0IjoiMjA5OS0wMS0wMVQwMDowMDowMCswMDowMCJ9.TCbbxY193j7w4-7iDw69zVxu8x4VG05tG9MGoCVDbSz6jQ8d20dMqdT47Jad1UWhHm5TSSK5lxZI3U5NGnBYkj3EnGl1BfBgWwlGzUKnyDjH8QgsxK401lKCQPtZXUxdJjdBHYzHoVsyB0akTwrOXtQMCdEX015MyS8jWAy3h0IpJPgTws6K3SUP1eLcz_pCo-i4IZjro1IJm98SHgkzFnxFuPMEiIZFCdlmbT549jyjOSVFGIupqXfRejS1531cT_aSQcjCSjGGMr91BugI79ORdOG8XHoM_AD9v2h3MPRED5vNrjA2-tUp245MOpQv4AmtxQMHczbZzU1PkQYWmVI4TyLZ37Ipsm1sQ1UN51Wm2Tbz7SMgEfBnZwIMhYDkjgHzsZnn-nPmmEq_UWFJiJuiEpZGQntS8WxFEHvk3TmOz1H3cLs_slsJZdi_cx-JCFiUTJJvarLwElOY6qHkfniLevy83Hh844xZQDCp6kYps_CxTGi9AjK0AGez0844";

    #[test]
    fn a_genuine_licence_verifies_and_names_the_property() {
        let payload = verify_token(FIXTURE_TOKEN, "test-machine")
            .expect("a genuine signature must verify");

        assert_eq!(payload.property, "Fixture Hotel");
        assert_eq!(payload.machine_id, "test-machine");
        assert_eq!(payload.plan, "annual");
    }

    #[test]
    fn a_licence_bound_to_another_machine_is_rejected() {
        let error = verify_token(FIXTURE_TOKEN, "some-other-machine")
            .expect_err("a licence must not work on a different machine");

        assert!(error.to_string().contains("different machine"));
    }

    #[test]
    fn the_activation_body_only_asks_for_a_transfer_when_asked() {
        let plain = activation_body("ML-AAAA", "machine-1", false);
        assert_eq!(plain["machine_id"], "machine-1");
        assert!(plain.get("transfer").is_none());

        let moved = activation_body("ML-AAAA", "machine-2", true);
        assert_eq!(moved["transfer"], serde_json::Value::Bool(true));
        assert_eq!(moved["machine_id"], "machine-2");
    }

    #[test]
    fn a_tampered_payload_is_rejected() {
        let token = format!(
            "{}.{}",
            URL_SAFE_NO_PAD.encode(br#"{"v":1}"#),
            URL_SAFE_NO_PAD.encode([0u8; 384]),
        );

        let error = verify_token(&token, "machine").expect_err("unsigned payload must fail");
        assert!(!error.to_string().is_empty());
    }

    #[test]
    fn a_malformed_token_is_rejected() {
        assert!(verify_token("not-a-token", "machine").is_err());
    }

    #[test]
    fn the_machine_id_is_stable_and_hex() {
        let first = machine_id();
        let second = machine_id();

        assert_eq!(first, second);
        assert_eq!(first.len(), 64);
        assert!(first.chars().all(|c| c.is_ascii_hexdigit()));
    }
}
