//! Persists which setup steps have finished.
//!
//! Spec 9.2 / plan Task 5 step 5: a wizard interrupted at step 6 must resume at
//! step 6, not restart. Setup runs on unreliable power in hotels, so the record
//! of progress lives in the application data directory and is rewritten after
//! every completed step.

use std::collections::BTreeSet;
use std::fs;
use std::path::{Path, PathBuf};

use anyhow::{Context, Result};
use serde::{Deserialize, Serialize};

#[derive(Default, Serialize, Deserialize)]
struct Persisted {
    completed: Vec<String>,
}

/// The set of finished step ids, backed by `setup-state.json` in the data dir.
pub struct SetupState {
    path: PathBuf,
    completed: BTreeSet<String>,
}

impl SetupState {
    pub fn load(data_dir: &Path) -> Result<Self> {
        let path = data_dir.join("setup-state.json");

        let completed = if path.exists() {
            let raw = fs::read_to_string(&path)
                .with_context(|| format!("failed to read {}", path.display()))?;
            // A corrupt file must not wedge the wizard; treat it as no progress.
            serde_json::from_str::<Persisted>(&raw)
                .unwrap_or_default()
                .completed
                .into_iter()
                .collect()
        } else {
            BTreeSet::new()
        };

        Ok(Self { path, completed })
    }

    pub fn is_complete(&self, id: &str) -> bool {
        self.completed.contains(id)
    }

    pub fn completed(&self) -> Vec<String> {
        self.completed.iter().cloned().collect()
    }

    /// Record a step as finished and flush immediately, so the mark survives a
    /// power loss between steps.
    pub fn mark_complete(&mut self, id: &str) -> Result<()> {
        if self.completed.insert(id.to_string()) {
            self.persist()?;
        }
        Ok(())
    }

    /// Forget all progress, for a "start over" affordance.
    pub fn reset(&mut self) -> Result<()> {
        self.completed.clear();
        if self.path.exists() {
            fs::remove_file(&self.path)
                .with_context(|| format!("failed to remove {}", self.path.display()))?;
        }
        Ok(())
    }

    fn persist(&self) -> Result<()> {
        if let Some(parent) = self.path.parent() {
            fs::create_dir_all(parent)
                .with_context(|| format!("failed to create {}", parent.display()))?;
        }

        let raw = serde_json::to_string_pretty(&Persisted {
            completed: self.completed.iter().cloned().collect(),
        })
        .context("failed to encode setup state")?;

        fs::write(&self.path, raw)
            .with_context(|| format!("failed to write {}", self.path.display()))?;

        Ok(())
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    fn temp_dir(tag: &str) -> PathBuf {
        let dir = std::env::temp_dir().join(format!("hotelms-state-{tag}-{}", std::process::id()));
        let _ = fs::remove_dir_all(&dir);
        dir
    }

    #[test]
    fn progress_survives_a_reload() {
        let dir = temp_dir("resume");

        let mut state = SetupState::load(&dir).unwrap();
        assert!(!state.is_complete("migrate"));
        state.mark_complete("migrate").unwrap();
        state.mark_complete("seed").unwrap();

        let reloaded = SetupState::load(&dir).unwrap();
        assert!(reloaded.is_complete("migrate"));
        assert!(reloaded.is_complete("seed"));
        assert!(!reloaded.is_complete("bind_network"));
        assert_eq!(reloaded.completed(), vec!["migrate", "seed"]);
    }

    #[test]
    fn reset_clears_the_record() {
        let dir = temp_dir("reset");
        let mut state = SetupState::load(&dir).unwrap();
        state.mark_complete("migrate").unwrap();
        state.reset().unwrap();

        assert!(SetupState::load(&dir).unwrap().completed().is_empty());
        assert!(!dir.join("setup-state.json").exists());
    }

    #[test]
    fn a_corrupt_file_is_treated_as_no_progress() {
        let dir = temp_dir("corrupt");
        fs::create_dir_all(&dir).unwrap();
        fs::write(dir.join("setup-state.json"), "{ not json").unwrap();

        assert!(SetupState::load(&dir).unwrap().completed().is_empty());
    }
}
