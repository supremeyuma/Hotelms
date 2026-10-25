# Offline POS Terminal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Run the existing Club POS on a terminal's local durable SQLite database, allow sales while disconnected and after restart, then reconcile exactly once through the LAN server first and cloud fallback.

**Architecture:** Terminal setup creates and migrates its local database, redeems one stable terminal identity separately with the LAN server and cloud, persists per-peer credentials in preference order, and starts FrankenPHP on loopback. Existing POS write capture and sync reconciliation remain authoritative; this plan composes them and proves offline/restart/convergence through real UI and database assertions.

**Tech Stack:** Laravel 12, SQLite, existing sync engine, Vue 3/Inertia, Tauri 2/Rust, bundled PHP and FrankenPHP, WebdriverIO.

**Spec:** `docs/superpowers/specs/2026-10-25-walkthrough-remediation-design.md`, section “4. Offline POS terminal” and the terminal walkthrough acceptance criteria. Use `docs/superpowers/plans/2026-10-05-phase-7-offline-terminal.md` for existing POS/offline contracts; do not rebuild its already implemented sync engine.

**Prerequisites:** Complete `2026-10-25-desktop-server-release-gate.md` and `2026-10-25-cloud-pairing-and-server-sync.md` first. The latter provides the owned `SyncWorker` used by this plan.

## Global Constraints

- The terminal's SQLite database is durable in app data and is the local authority while disconnected.
- The local terminal HTTP service binds only to loopback; it must not be reachable from the LAN.
- One stable terminal node ID is registered separately with LAN and cloud, with a separate credential per peer.
- Sync preference is LAN server first and cloud fallback.
- Local writes and sales continue while peers are unreachable; a restart preserves sales and pending sync changes.
- Reconciliation produces exactly one sale with converged stock/reporting effects.
- Sale submission is immediately non-repeatable while processing and reports its pending/completed state.
- No live cloud, external payment service, or production/development database is used.
- Physical receipt-printer certification is out of scope.

---

## File Structure

- `desktop/src-tauri/src/setup/mod.rs` owns terminal database creation, dual registration, snapshot order, and setup progress.
- `desktop/src-tauri/src/lib.rs` defines the Tauri terminal input and retains setup resources.
- `desktop/src-tauri/src/lib.rs` retains the terminal runtime/worker and manages setup completion/shutdown.
- `desktop/src-tauri/src/setup/state.rs` records the installed role so an app restart can restore terminal serving without spending pairing codes again.
- `desktop/src-tauri/src/envfile.rs` persists ordered peer JSON and separate credentials.
- `desktop/src-tauri/src/runtime.rs` starts the terminal's FrankenPHP process bound to `127.0.0.1`.
- `desktop/src/main.ts` collects LAN/cloud pairing inputs and opens the terminal POS surface.
- `config/sync.php` already accepts an ordered peer map with per-peer credentials; preserve that contract.
- `routes/club.php` and `resources/js/Pages/ClubPos/Dashboard.vue` are the existing POS entry and sale UI.
- `resources/js/Components/ClubPos/OfflineBanner.vue`, `resources/js/Components/ClubPos/PaymentModal.vue`, and `app/Http/Controllers/ClubPos/ConnectivityController.php` already provide connectivity and non-repeatable payment behavior; preserve and verify them.
- `tests/Feature/Offline/` covers durable offline sale and exact reconciliation where new regression coverage is required.
- `desktop/e2e/specs/` owns the isolated multi-process terminal walkthrough.

## Task 1: Make terminal peer configuration dual-credential and ordered

**Files:**
- Modify: `desktop/src-tauri/src/setup/mod.rs`
- Modify: `desktop/src-tauri/src/envfile.rs`
- Test: Rust setup/envfile unit tests.

**Interfaces:**
- `TerminalSetupInput` in `desktop/src-tauri/src/lib.rs` contains `lanServerUrl`, `lanPairingCode`, `cloudUrl`, and `cloudPairingCode`.
- Each registration response supplies `peer_node_id`; persisted `SYNC_PEERS` is an ordered JSON map keyed by peer node ID, each entry `{ "base_url": "...", "credential": "..." }`, with LAN first and cloud second.
- Produces: terminal registration uses the same persisted node ID for each peer and sends `node_type: "terminal"`.

- [ ] **Step 1: Add tests for dual peer ordering and credential separation**

For two different peer URLs and credentials, assert JSON round-trips into `config('sync.peers')` with LAN first and each correct credential; assert no secret is written to progress messages.

```rust
assert_eq!(peers[0].base_url, "http://127.0.0.1:8000");
assert_eq!(peers[0].credential, lan_credential);
assert_eq!(peers[1].base_url, "https://cloud.example.test");
assert_eq!(peers[1].credential, cloud_credential);
```

- [ ] **Step 2: Validate setup input pairing**

Require URL/code pairs for both peers and at least one available bootstrap peer. Require HTTPS for the cloud URL except HTTP loopback in isolated tests. Accept HTTP or HTTPS for the explicitly entered LAN server URL, including a private LAN address. Keep the canonical property ID check at 1 and reject mismatch; never match by name.

- [ ] **Step 3: Register the stable node with each peer**

Redeem LAN first and cloud second using the same existing `SYNC_NODE_ID`, `property_code`, node name, and `node_type: "terminal"`. Collect each peer's returned node ID/credential. If one endpoint fails, preserve explicit per-peer failure state and do not claim a missing peer is linked.

- [ ] **Step 4: Run Rust tests**

Run: `cargo test --manifest-path desktop/src-tauri/Cargo.toml`

Expected: URL validation and dual credential/order tests pass; the stable node ID is reused for both endpoints.

- [ ] **Step 5: Commit dual-peer registration**

```powershell
git add desktop/src-tauri/src/setup/mod.rs desktop/src-tauri/src/envfile.rs
git commit -m "feat: pair pos terminal with lan and cloud peers" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```

## Task 2: Verify snapshot fallback without local peer metadata

**Files:**
- Test: `tests/Feature/Sync/SyncRefreshReferenceCommandTest.php`

**Interfaces:**
- Produces: `sync:refresh-reference` tries the configured LAN peer first, then cloud if the LAN peer is unavailable; local setup records which peer supplied the successful snapshot.

- [ ] **Step 1: Add a fresh-terminal peer-order regression test**

The existing command orders peers by node type when local `sync_nodes` rows exist, preserves configured order for unknown peers, and falls through when the first peer is unreachable. Add a test with no local `sync_nodes` rows and a peer map inserted LAN-first/cloud-second. Verify LAN is called first and a successful LAN snapshot prevents a cloud request. Keep the existing tests for transport fallback and all-peer failure intact.

```php
$userRow = [
    'id' => 42,
    'uuid' => (string) Str::uuid(),
    'name' => 'LAN User',
    'email' => 'lan@example.test',
    'password' => Hash::make('secret'),
];
$cloudRow = [
    'id' => 99,
    'uuid' => (string) Str::uuid(),
    'name' => 'Cloud User',
    'email' => 'cloud@example.test',
    'password' => Hash::make('secret'),
];

config(['sync.peers' => [
    'lan-node' => ['base_url' => 'http://lan.test', 'credential' => 'lan-secret'],
    'cloud-node' => ['base_url' => 'https://cloud.test', 'credential' => 'cloud-secret'],
]]);

Http::fake([
    'http://lan.test/*' => Http::response($this->payload(['users' => [$userRow]]), 200),
    'https://cloud.test/*' => Http::response($this->payload(['users' => [$cloudRow]]), 200),
]);

$this->artisan('sync:refresh-reference')->assertExitCode(0);
$requests = Http::recorded();
$this->assertCount(1, $requests);
$this->assertSame('lan.test', parse_url($requests[0][0]->url(), PHP_URL_HOST));
$this->assertDatabaseHas('users', ['id' => 42, 'email' => 'lan@example.test']);
$this->assertDatabaseMissing('users', ['id' => 99]);
```

- [ ] **Step 2: Keep existing snapshot command contract**

Do not change the existing `SYNC_PEERS` parsing or `sync:refresh-reference` peer traversal unless the new test proves a defect. Preserve the existing transport fallback and first-answer-wins behavior.

- [ ] **Step 3: Run focused tests**

Run: `php artisan test tests/Feature/Sync/SyncRefreshReferenceCommandTest.php tests/Feature/Offline`

Run: `cargo test --manifest-path desktop/src-tauri/Cargo.toml`

Expected: snapshot order/fallback tests pass and unrelated reconciliation behavior is unchanged.

- [ ] **Step 4: Commit initial snapshot failover**

```powershell
git add tests/Feature/Sync/SyncRefreshReferenceCommandTest.php
git commit -m "test: verify lan-first terminal snapshot fallback" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```

## Task 3: Start a local terminal runtime bound to loopback

**Files:**
- Modify: `desktop/src-tauri/src/setup/mod.rs`
- Modify: `desktop/src-tauri/src/runtime.rs`
- Modify: `desktop/src-tauri/src/lib.rs`
- Test: Rust runtime/setup tests.

**Interfaces:**
- `Runtime::start(app_root, frankenphp, "127.0.0.1", http_port, env, log_path)` returns an owned child after readiness succeeds.
- `TerminalBoot` retains the runtime and terminal environment.
- `SetupHandle` retains the terminal runtime/worker as well as existing server resources.

- [ ] **Step 1: Test terminal host and port configuration**

Assert terminal starts on `127.0.0.1`, server remains on `0.0.0.0`, and readiness failure is reported rather than displaying a usable POS link.

- [ ] **Step 2: Remove the reserved-port no-op**

Use `options.http_port` to start FrankenPHP after migrations and initial snapshot. Store the runtime in `TerminalBoot`; ensure every failure path drops any child started by that setup attempt. Create `hotelms.sqlite` only when it does not exist; never truncate a database on setup retry. Persist `SYNC_NODE_TYPE=terminal`, `SYNC_PROPERTY_ID=1`, `SYNC_ENABLED=true`, and the ordered peer map in `.env`.

- [ ] **Step 3: Retain terminal runtime for the app lifetime**

Replace the server-only active resource with a role-aware owned state; preserve tray behavior for LAN server and close behavior for terminal. Ensure shutdown stops only this app's FrankenPHP child.

- [ ] **Step 4: Verify Rust build and bind address**

Run: `cargo test --manifest-path desktop/src-tauri/Cargo.toml`

Run: `cargo check --manifest-path desktop/src-tauri/Cargo.toml --features e2e`

Expected: terminal listener responds on loopback and does not listen on `0.0.0.0`.

- [ ] **Step 5: Commit loopback serving**

```powershell
git add desktop/src-tauri/src/setup/mod.rs desktop/src-tauri/src/runtime.rs desktop/src-tauri/src/lib.rs
git commit -m "feat: serve terminal app on loopback" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```

## Task 4: Restore the installed terminal after app restart

**Files:**
- Modify: `desktop/src-tauri/src/setup/state.rs`
- Modify: `desktop/src-tauri/src/lib.rs`
- Modify: `desktop/src-tauri/src/envfile.rs`
- Test: Rust setup-state and startup tests.

**Interfaces:**
- Persisted setup state records `installed_role: "terminal"` only after database migration, both peer registrations, snapshot, and runtime readiness have succeeded.
- `setup_resume_terminal` loads the existing `.env` and database, starts FrankenPHP on the persisted loopback port, and starts the terminal sync worker without re-registering or truncating the database.

- [ ] **Step 1: Add persisted-role and resume tests**

Assert a terminal role survives reloading setup state; incomplete setup does not auto-resume; complete setup resumes from existing peer credentials and does not call registration endpoints again.

- [ ] **Step 2: Persist the terminal role and local HTTP port after readiness**

Write the role marker only after the full terminal setup succeeds. Store the port in the app-data environment file; use atomic writes and preserve peer credential secrecy.

- [ ] **Step 3: Add a Tauri resume command**

Load and validate the persisted role, SQLite file, environment, and peer configuration; start only the terminal's loopback runtime/worker; return a useful error if the saved setup is corrupt or incomplete. Do not call the pairing endpoints during resume.

- [ ] **Step 4: Resume at application startup**

Have the desktop entry flow query setup status before rendering role selection. When a completed terminal install is present, invoke the resume command and navigate the existing window to `/club/pos` at `http://127.0.0.1` using the persisted HTTP port, only after the readiness probe succeeds.

- [ ] **Step 5: Run Rust and browser setup checks**

Run: `cargo test --manifest-path desktop/src-tauri/Cargo.toml`

Run: `npm --prefix desktop run e2e:fast`

Expected: fresh installs retain role selection; completed terminal installs resume into the POS without prompting for spent pairing codes.

- [ ] **Step 6: Commit terminal resume**

```powershell
git add desktop/src-tauri/src/setup/state.rs desktop/src-tauri/src/lib.rs desktop/src-tauri/src/envfile.rs desktop/src/main.ts desktop/e2e/specs-browser/wizard.spec.ts
git commit -m "feat: resume terminal service after app restart" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```

## Task 5: Complete terminal setup UI and open the POS dashboard

**Files:**
- Modify: `desktop/src/main.ts`
- Modify: `desktop/src-tauri/src/lib.rs`
- Test: `desktop/e2e/specs-browser/` and native E2E setup coverage.

**Interfaces:**
- `setup_run_terminal` receives the four URL/code values and returns progress through the existing setup step events.
- Produces: successful completion opens the POS dashboard at the configured loopback HTTP port; failure shows which peer step failed without exposing submitted codes or credentials.

- [ ] **Step 1: Add the dual-peer terminal form**

Collect LAN URL/code and cloud URL/code in separate labeled fields. Display that LAN is preferred and cloud is fallback. Prevent double submission immediately and show progress while migrations, pairing, snapshot, and runtime readiness execute.

- [ ] **Step 2: Replace the “offline selling not yet available” completion copy**

On success, report the local POS URL and offline-ready state; open the URL only after FrankenPHP passes readiness. On failure, retain form values except secrets, show the step-specific error, and allow a retry.

- [ ] **Step 3: Verify browser-mode wizard contracts**

Run: `npm --prefix desktop run e2e:fast`

Expected: terminal fields, disabled/loading state, safe error display, and completion navigation work with the setup command mock.

- [ ] **Step 4: Commit terminal setup UI**

```powershell
git add desktop/src/main.ts desktop/src-tauri/src/lib.rs desktop/e2e/specs-browser/wizard.spec.ts
git commit -m "feat: complete offline pos terminal setup" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```

## Task 6: Start sync for terminal peers and verify connectivity feedback

**Files:**
- Reuse: `desktop/src-tauri/src/sync_worker.rs` from the cloud/server plan.
- Modify: `desktop/src-tauri/src/lib.rs`
- Test: `tests/Feature/Offline/OfflineSaleTest.php`, `tests/Feature/Offline/MidShiftRestartTest.php`, and `tests/Feature/Offline/ReconciliationTest.php`.

**Interfaces:**
- The same owned sync-worker supervisor runs on a terminal when `SYNC_ENABLED=true` and `SYNC_PEERS` is configured.
- The POS shows online/offline status from the existing `GET /club/pos/connectivity`; sale success while offline remains local and is labeled pending sync.

- [ ] **Step 1: Wire terminal peer configuration to the worker**

Start the existing worker after the local runtime is ready; preserve LAN-first order; stop it with the Tauri-owned terminal resources.

- [ ] **Step 2: Verify sale submit is non-repeatable**

Verify the existing `PaymentModal` lock disables the payment button immediately after valid submission, displays `Processing...`, prevents a second POST, and releases on success or failure. Verify `Dashboard.vue` surfaces the queued state from the server response. Change those existing components only if these assertions reveal a concrete regression.

- [ ] **Step 3: Run focused POS/offline checks**

Run: `php artisan test tests/Feature/Offline/OfflineSaleTest.php tests/Feature/Offline/MidShiftRestartTest.php tests/Feature/Offline/ReconciliationTest.php`

Run: `npm run build`

Expected: existing connectivity banner and sale UI compile; offline sale remains accepted and marked pending.

- [ ] **Step 4: Commit worker and POS status integration**

```powershell
git add desktop/src-tauri/src/lib.rs tests/Feature/Offline/OfflineSaleTest.php tests/Feature/Offline/MidShiftRestartTest.php tests/Feature/Offline/ReconciliationTest.php
git commit -m "feat: show terminal connectivity and pending sync" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```

## Task 7: Prove local sale durability across restart and exactly-once reconciliation

**Files:**
- Create or extend: `desktop/e2e/specs/offline-terminal.spec.ts`
- Modify: `desktop/e2e/helpers/` only for explicit process/data ownership.
- Test: existing `tests/Feature/Offline/OfflineSaleTest.php`, `MidShiftRestartTest.php`, and `ReconciliationTest.php`.

- [ ] **Step 1: Prepare isolated peer fixtures and accounts**

Start local cloud and LAN server instances with separate test databases, seed one property and a POS-authorized cashier, and issue one terminal code per peer. Track exact child PIDs and app-data paths for cleanup.

- [ ] **Step 2: Pair the terminal and complete an online sale**

Use the real desktop wizard and local POS UI. Assert the local SQLite docket/payment rows and peer node IDs/credentials exist; do not print any secret values.

- [ ] **Step 3: Stop only the test peers and complete a sale offline**

Stop the tracked cloud and LAN processes. Verify the offline banner; create and pay a docket, confirm exactly one local sale and pending sync changes, and confirm repeated submit is blocked while the request is active.

- [ ] **Step 4: Restart the terminal app without clearing its data**

Close/relaunch only the E2E terminal binary against the same isolated app-data directory. Verify SQLite still contains the paid docket and pending changes and the POS UI can authenticate and load.

- [ ] **Step 5: Restore peers and assert exactly-once convergence**

Start LAN and cloud test peers; allow the worker to run. Assert each sale appears once on the LAN server and cloud, stock decrements once, reporting totals include each payment once, and a repeated sync cycle makes no further change.

- [ ] **Step 6: Run complete focused validation and cleanup**

Run: `php artisan test tests/Feature/Offline/OfflineSaleTest.php tests/Feature/Offline/MidShiftRestartTest.php tests/Feature/Offline/ReconciliationTest.php`

Run: `npm --prefix desktop run e2e -- --spec ./e2e/specs/offline-terminal.spec.ts`

Expected: all assertions pass; only test-owned processes/data are stopped/removed; unrelated app data and developer processes remain untouched.

- [ ] **Step 7: Commit the offline POS walkthrough**

```powershell
git add desktop/e2e/specs/offline-terminal.spec.ts tests/Feature/Offline/OfflineSaleTest.php tests/Feature/Offline/MidShiftRestartTest.php tests/Feature/Offline/ReconciliationTest.php
git commit -m "test: verify offline pos restart and reconciliation" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```
