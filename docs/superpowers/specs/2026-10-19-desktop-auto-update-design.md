# Desktop auto-update — design

**Date:** 2026-10-19 · **Status:** approved, pending plan
**Scope:** the desktop application (all three roles). The cloud is excluded.

## Problem

A released update currently reaches nobody. The desktop build has no release
path: `.github/workflows/desktop-build.yml` runs on push, executes
`cargo build --release`, and **discards the binary** — no artifact upload, no
installer, no published release, and no version bump beyond `0.1.0` in
`tauri.conf.json`. The desktop crate depends only on `tauri` and
`tauri-plugin-opener`; there is no updater plugin, no update endpoint and no
signing key.

The owner's requirement: **when an update is released, the app updates itself,
with the LAN server waiting for owner approval — and the download must already
have happened by then, so approval is an instant install rather than a wait.**

## Decisions (made with the owner)

1. **Approval is per role.** Thin clients update automatically; the **LAN
   server stays owner-approved**, because restarting it takes the site offline.
2. **Download is never gated.** Every node silently checks, downloads and
   verifies a release in the background. Only *installation* is gated.
3. **A terminal applies its update on the next app restart.** Terminals hold a
   local database (`plan_for(SetupRole::Terminal)` = create database → migrate →
   pull snapshot → register), so they must not restart mid-sale — but they
   also should not require an owner ceremony. Applying at next start means it
   lands at a moment when no sale can be open, and instantly, because the bytes
   are already local.
4. **Distribution is GitHub Releases** (the repository is public, so hosting
   costs nothing and every node can already reach it — terminals make outbound
   HTTPS to the cloud today). Because the repo is public, installers and version
   history are publicly downloadable. **That is accepted**: Tauri's signature
   makes a tampered artifact unusable, and the artifact is an application the
   hotel runs, not a secret. Hiding them later would be a distribution change,
   not a policy change.
5. **No Windows Authenticode certificate**, same as today. Tauri's own
   signature protects the artifact; SmartScreen will still show "unknown
   publisher" on install. Adding a certificate later is a cost decision, not a
   design change.

## Current state (verified at HEAD)

| Fact | Evidence |
|---|---|
| No release is ever published | `desktop-build.yml` ends at `cargo build --release`; no `upload-artifact`, no `release` |
| Version is `0.1.0` in three places | `tauri.conf.json:3`, `desktop/package.json`, `desktop/src-tauri/Cargo.toml:3` |
| No updater plugin | `desktop/src-tauri/Cargo.toml` → only `tauri-plugin-opener` |
| No signing configuration | `tauri.conf.json` `bundle` block has no `signingIdentity`; nothing for updater keys |
| The print bridge is not bundled | `desktop/print-bridge/` is a separate crate; `tauri.conf.json` declares no external binary or resource |
| Roles are known at runtime | `lib.rs:56-58` → `server` / `terminal` / `staff_client` |
| Migrations run on every wizard start | `setup/mod.rs:270-279` ("Migrations run every time: they are idempotent") |
| Binaries are unsigned | `tauri.conf.json` bundle block, `desktop-build.yml` build step |

> Phase 8 ruling **R9** ("no new CI workflow this phase") applied to Phase 8
> only. A release workflow is explicitly out of that constraint.

## Design

### Release chain

1. **Version bump** in one commit across `tauri.conf.json`, `desktop/package.json`
   and `desktop/src-tauri/Cargo.toml`. All three must agree — the updater
   compares semver against the app's own version.
2. **Tag `vX.Y.Z` on `main`** starts the release workflow.
3. The workflow builds the wizard frontend, then runs **`tauri build`**, which
   produces a real **installer** (NSIS/MSI on Windows) instead of a bare binary.
4. It signs the installer with the **updater signing key** and emits the
   **version manifest** Tauri's client expects: target version, artifact URL,
   base64 signature, optional release notes.
5. Installer and manifest are uploaded to a **GitHub Release** for that tag. The
   manifest is published as `latest.json` on that release, which is what
   `https://github.com/<owner>/<repo>/releases/latest/download/latest.json`
   resolves to — the endpoint the client is configured against, so no server of
   our own is ever involved.

**Key custody.** The private signing key is a repository secret used only by the
release workflow; the matching public key is compiled into the app via
`tauri.conf.json`. Losing the private key is unrecoverable in the strict sense
— installed copies will reject anything signed with a replacement key, so no
deployed client could ever update again. The owner therefore keeps an
independent backup of the keypair off CI.

### Client

Add `tauri-plugin-updater`, pointed at the manifest URL on GitHub Releases with
the public key pinned in configuration. On a successful check it downloads and
verifies the artifact into local storage. **Verification is part of download:
bytes that fail the signature check are discarded, never staged.**

### Per-role install policy

| Role | Holds data | Download | Install |
|---|---|---|---|
| `staff_client` | none — points at a server | silent, background | **automatic** (restart the window; nothing can be lost) |
| `terminal` | local database, pending sync changes | silent, background | **on next app restart**, with a visible "restart to finish updating" indicator |
| `server` (LAN server) | embedded Postgres, serves the site | silent, background | **owner-approved restart**; applies instantly because the download is already complete |

The LAN server never installs on its own under any condition. That is the
invariant the owner chose.

### Prerequisites

- **Bundle the print bridge.** `desktop/print-bridge` is not part of the
  installer. Until it is, an update ships a shell that cannot print. It must be
  attached as a Tauri external binary (named for the target triple) so it is
  installed, replaced and removed together with the app.
- **The signature keypair** described above must exist before the first
  release, and its public half is baked in from that moment on.

### Migrations

An updated LAN server runs `migrate --force` on restart (the wizard already
migrates on every start). This is the sharpest edge in the design: a released
migration that fails would leave a live site on an unpromoted schema. Owner
approval on that role is the mitigation, and the release process must state
plainly that migrations run at install time on the LAN server.

## Failure handling

- **Update check fails** (offline, GitHub unreachable): silent. The app runs on
  its current version and retries on the next interval. No user-facing error
  for a routine network problem.
- **Signature mismatch:** discard the download, log it, retry on the next
  interval, surface it in the app's diagnostics. Never install.
- **Download incomplete:** not staged; re-download rather than resume into a
  half-written artifact.
- **Owner never approves:** harmless. The LAN server keeps running its current
  version indefinitely; the staged update may be superseded by a newer one.
- **Disk full / download impossible:** the app keeps running; the indicator
  shows the update could not be prepared.

## Non-goals

- No auto-update for the **cloud** — Coolify deployment stays owner-run (R1).
- No update server of our own; GitHub Releases is the host.
- No staged rollout, no canary cohort, no update-pause scheduling. YAGNI — the
  fleet is a handful of machines.
- No Authenticode certificate in this pass.
- No auto-restart of a terminal mid-shift.
- **No silent upgrade of a schema while the owner is away** — the LAN server
  rule forbids it.

## Testing

- Unit: the install-policy decision per role (`server` → never automatic,
  `terminal` → next restart, `staff_client` → automatic) and manifest/semver
  comparison. Policies are the thing most likely to regress silently, so they
  are pinned by tests.
- Unit: a manifest whose signature does not verify is rejected, and a valid one
  stages — without hitting the network (the artifact source is faked).
- Desktop crate tests in `cargo test` for the policy and verification logic,
  since `desktop-build.yml` already runs them.
- The release workflow itself is validated by a release, so the first version is
  `0.1.0 → 0.2.0` and is exercised before any later release is relied on.

## Exit criteria

- A tag on `main` publishes an installer and a manifest to a GitHub Release
  without manual steps.
- A terminal and a staff client on `0.1.0` reach `0.2.0` with no owner action
  and no data loss, the terminal applying at its next restart.
- The LAN server downloads `0.2.0`, shows it as ready, and installs only when
  the owner approves — install taking seconds, not minutes.
- A tampered artifact is rejected and logged, never installed.
- The print bridge travels with the app inside the installer.
- The signing keypair has an owner-held backup that is not in CI.
