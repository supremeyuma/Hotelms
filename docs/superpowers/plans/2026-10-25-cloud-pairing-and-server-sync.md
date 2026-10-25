# Cloud Pairing and Desktop Server Sync Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let authorized administrators create one-time pairing codes, register the desktop LAN server to its canonical cloud property, and supervise real cloud synchronization without blocking local hotel operations.

**Architecture:** Add one admin-facing Inertia page over the existing pairing redemption endpoint, store only hashes of pairing codes, and have the Tauri server persist a returned peer credential only after successful redemption. A dedicated Rust-owned child supervisor runs the existing `sync:run` command only for configured peers and reports observable status; it does not start Laravel's general scheduler.

**Tech Stack:** Laravel 12, Inertia.js, Vue 3, PHPUnit, Rust/Tauri 2, bundled PHP/FrankenPHP, SQLite/PostgreSQL-compatible migrations.

**Spec:** `docs/superpowers/specs/2026-10-25-walkthrough-remediation-design.md`, sections “2. Cloud pairing-code administration”, “3. Desktop server cloud registration and sync”, and the corresponding walkthrough steps.

**Prerequisites:** Complete `2026-10-25-desktop-server-release-gate.md` first so cloud integration runs on the rebuilt native E2E binary and current staged application.

## Global Constraints

- Single-property scope only; the cloud property is canonical and the current local property ID is 1.
- Never infer a property match by name or rewrite unrelated property IDs.
- Pairing codes are single-use, expire after the existing 15-minute default, are stored as one-way hashes, are displayed once, and are never logged.
- Require HTTPS for non-loopback cloud URLs; permit HTTP only for loopback isolated tests.
- Pairing failure leaves local LAN serving available and is reported as retryable.
- Start only the dedicated sync worker when synchronization is enabled and a peer is configured; do not start unrelated Laravel scheduled jobs.
- The app stops only its own worker on shutdown and never logs or redisplays credentials.
- All tests use isolated local services and databases; do not connect to the live cloud.

---

## File Structure

- `database/migrations/` changes the pairing-code storage to a hash-only representation with a safe upgrade path.
- `app/Http/Controllers/Sync/NodeRegistrationController.php` redeems the submitted plaintext by hashing it before lookup.
- `app/Http/Controllers/Admin/SyncPairingCodeController.php` authorizes and issues one-time codes for the installation's canonical property.
- `app/Http/Controllers/Admin/SyncNodeStatusController.php` exposes secret-free peer/worker status.
- `app/Console/Commands/BindCanonicalProperty.php` updates only local property ID 1 from the validated cloud registration response.
- `routes/admin.php` exposes the protected page, create action, and status page.
- `resources/js/Pages/Admin/Sync/PairingCodes.vue` displays the single-use result and never lists plaintext codes.
- `resources/js/Pages/Admin/Sync/Status.vue` displays link and sync-worker health without credentials.
- `database/seeders/WalkthroughCloudSeeder.php` creates only disposable walkthrough roles, users, and property data.
- `desktop/src-tauri/src/setup/` validates cloud URL/property and redeems a `lan_server` code.
- `desktop/src-tauri/src/envfile.rs` persists peer URL and credential data in the protected app-data configuration.
- `desktop/src-tauri/src/sync_worker.rs` owns the dedicated supervised Artisan child and status file.
- `desktop/src-tauri/src/lib.rs` retains and stops both server runtime and worker.
- `desktop/src/main.ts` collects optional cloud URL/code and renders retry/error status.
- `tests/Feature/Sync/` and `tests/Feature/Cloud/` cover authorization, code lifecycle, registration, property binding, and non-disclosure.
- `desktop/e2e/` exercises the real cloud admin UI and local server pairing in the isolated walkthrough.

## Task 1: Store pairing codes as hashes

**Files:**
- Create: `database/migrations/2026_10_25_000001_hash_sync_pairing_codes.php`
- Modify: `app/Http/Controllers/Sync/NodeRegistrationController.php`
- Modify: `app/Console/Commands/SyncPairingCode.php`
- Test: `tests/Feature/Sync/NodeRegistrationTest.php` and `tests/Feature/Sync/NodePairingCommandsTest.php`.

**Interfaces:**
- Consumes: submitted `pairing_code` plaintext.
- Produces: `sync_pairing_codes.code_hash`, a unique SHA-256 hex digest; redemption hashes normalized input before selecting an unused, unexpired row.
- Registration response contains the new node's `node_id`, responding server's `peer_node_id`, one-time credential, cloud URL, and canonical property ID/name.

- [ ] **Step 1: Add lifecycle tests before changing persistence**

Cover valid redemption, expired/reused/wrong-property indistinguishable rejection, concurrent single-use behavior, and absence of plaintext from the database. The one-time display is covered by Task 2.

```php
use Illuminate\Support\Facades\Schema;

$response = $this->postJson('/api/sync/register', $payload);
$response->assertCreated()->assertJsonStructure([
    'node_id', 'peer_node_id', 'credential', 'cloud_url', 'property_id', 'property_name',
]);
$this->assertDatabaseHas('sync_pairing_codes', [
    'code_hash' => hash('sha256', strtoupper($payload['pairing_code'])),
]);
$this->assertFalse(Schema::hasColumn('sync_pairing_codes', 'code'));
```

- [ ] **Step 2: Migrate existing plaintext rows without leaving plaintext behind**

Before implementation, confirm `2026_10_25_000001_hash_sync_pairing_codes.php` does not collide with an existing migration. Add nullable `code_hash`, fill it in bounded `chunkById(100)` batches using `hash('sha256', strtoupper(trim($row->code)))`, add a unique index, then drop `code`. In `down()`, invalidate and delete existing pairing rows, restore the old `code` column and its unique index, and remove `code_hash`; the old plaintext values cannot be recovered from hashes, and expired credentials must never be recreated.

- [ ] **Step 3: Hash on issue and lookup**

Generate a cryptographically random uppercase code, store only `hash('sha256', $code)`, return plaintext once from the admin create action, and update redemption to query the normalized input digest. Preserve the existing atomic `used_at` update and indistinguishable rejection body.

- [ ] **Step 4: Run focused migration and registration tests**

Run: `php artisan test tests/Feature/Sync/NodeRegistrationTest.php tests/Feature/Sync/NodePairingCommandsTest.php`

Expected: lifecycle tests pass on the configured SQLite test database; `php artisan migrate:fresh --env=testing` can build the schema cleanly.

- [ ] **Step 5: Commit the hash-only storage change**

```powershell
git add database/migrations/2026_10_25_000001_hash_sync_pairing_codes.php app/Http/Controllers/Sync/NodeRegistrationController.php app/Console/Commands/SyncPairingCode.php tests/Feature/Sync/NodeRegistrationTest.php tests/Feature/Sync/NodePairingCommandsTest.php
git commit -m "fix: hash one-time sync pairing codes" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```

## Task 2: Add an authorized pairing-code admin surface

**Files:**
- Create: `app/Http/Controllers/Admin/SyncPairingCodeController.php`
- Create: `resources/js/Pages/Admin/Sync/PairingCodes.vue`
- Modify: `routes/admin.php`
- Modify: `resources/js/Components/Admin/AdminSidebar.vue`
- Test: `tests/Feature/Cloud/SyncPairingCodeAdminTest.php`

**Interfaces:**
- Produces: authenticated `md` and `superuser` can open the page and issue a code; `manager` cannot issue credentials.
- Form input: `{ node_type: 'lan_server' | 'terminal' }`; code binds to the current installation's canonical property and uses the existing 15-minute expiry.

- [ ] **Step 1: Test access and validation**

Test that unauthenticated users are redirected, `manager` receives 403 for page/create, `md` and `superuser` can create, invalid node type is rejected, and the row binds to the configured canonical property.

- [ ] **Step 2: Add protected routes and controller**

Add `GET /admin/sync/pairing-codes` and `POST /admin/sync/pairing-codes` inside the existing `auth`/admin route group, with a nested `role:md|superuser` gate. The controller obtains the one canonical property from the local installation, creates a random code and its hash, sets `expires_at = now()->addMinutes(15)`, and returns plaintext only as a one-time flash prop on the redirect.

- [ ] **Step 3: Build the Inertia form and one-time result**

Add a restricted “Sync pairing” link to `AdminSidebar.vue`. Provide an allowed node-type selector with `data-testid="sync-pairing-node-type"`, a create button with immediate disabled/loading state, and a result element with `data-testid="sync-pairing-code"`. Render the returned code only when the one-time flash prop exists; show copy instructions and expiry, then do not include it in an ordinary list or subsequent response.

- [ ] **Step 4: Verify real browser behavior and role denial**

Run: `php artisan test tests/Feature/Cloud/SyncPairingCodeAdminTest.php`

Run: `npm run build`

Expected: authorized users can create and see a code exactly once; manager cannot access the create surface; no credential or plaintext code appears in logs, page props after flash consumption, or a list response.

- [ ] **Step 5: Commit the cloud admin page**

```powershell
git add app/Http/Controllers/Admin/SyncPairingCodeController.php routes/admin.php resources/js/Pages/Admin/Sync/PairingCodes.vue resources/js/Components/Admin/AdminSidebar.vue tests/Feature/Cloud/SyncPairingCodeAdminTest.php
git commit -m "feat: add authorized sync pairing admin page" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```

## Task 3: Validate the cloud URL and registration contract in Rust

**Files:**
- Modify: `desktop/src-tauri/src/setup/mod.rs`
- Modify: `desktop/src-tauri/src/lib.rs`
- Test: Rust unit tests in the setup module.

**Interfaces:**
- `ServerSetupInput` gains optional `cloudUrl: Option<String>` and `cloudPairingCode: Option<String>`.
- `ServerOptions` gains matching validated optional fields.
- Registration uses existing `POST /api/sync/register` JSON fields: `property_code`, `pairing_code`, `node_name`, `node_type: "lan_server"`, and the stable persisted `node_id`.
- Produces: `CloudRegistration { node_id: String, peer_node_id: String, credential: String, cloud_url: String, property_id: i64, property_name: String }` only after a 201 response.

- [ ] **Step 1: Add URL and property validation tests**

Cover HTTPS public URL acceptance, HTTP loopback acceptance, rejection of HTTP non-loopback, missing URL/code pairing, whitespace/trailing slash normalization, and property ID mismatch.

- [ ] **Step 2: Add optional cloud fields to the setup input**

Require URL and pairing code together when either is supplied. Preserve a valid local-only setup when both are absent.

- [ ] **Step 3: Generalize registration to `lan_server`**

Reuse the stable `SYNC_NODE_ID`; send `property_code: 1` and `node_type: "lan_server"`; bound response size/time; reject non-success, malformed JSON, empty credentials, a mismatched `node_id`, missing `peer_node_id`, or a `property_id` other than 1. Error text contains status and safe response detail but never the submitted code or returned credential.

- [ ] **Step 4: Run Rust tests and compile the E2E binary**

Run: `cargo test --manifest-path desktop/src-tauri/Cargo.toml`

Run: `cargo check --manifest-path desktop/src-tauri/Cargo.toml --features e2e`

Expected: URL/registration tests pass and the full Tauri crate checks.

- [ ] **Step 5: Commit validated registration**

```powershell
git add desktop/src-tauri/src/setup/mod.rs desktop/src-tauri/src/lib.rs
git commit -m "feat: pair desktop server with cloud property" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```

## Task 4: Persist the server's cloud peer only after successful pairing

**Files:**
- Create: `app/Console/Commands/BindCanonicalProperty.php`
- Modify: `app/Http/Controllers/Sync/NodeRegistrationController.php`
- Modify: `desktop/src-tauri/src/envfile.rs`
- Modify: `desktop/src-tauri/src/setup/mod.rs`
- Modify: `desktop/src/main.ts`
- Modify: `desktop/src-tauri/src/lib.rs`
- Test: `tests/Feature/Sync/BindCanonicalPropertyTest.php`
- Test: Rust serialization/envfile tests and desktop wizard browser tests.

**Interfaces:**
- Produces: one `SYNC_PEERS` JSON map entry keyed by the response's `peer_node_id` with `{ "base_url": "...", "credential": "..." }`; `SYNC_ENABLED=true` only after successful persistence.
- Does not change the LAN server's normal operation if cloud fields are absent or registration fails.

- [ ] **Step 1: Test peer serialization and secret redaction**

Ensure JSON correctly escapes URL/credential strings and test diagnostics never format the credential.

- [ ] **Step 2: Return canonical property information and update the local property before admin creation**

Extend the registration response with `peer_node_id`, `property_id`, and the name read from the pairing code's property. Add the Artisan command `hotelms:bind-property` with required `--id` and `--name` options; pass `registration.property_name` as the name argument. Test that it updates only row ID 1. During fresh setup, redeem the code after migration/seed but before `hotelms:create-admin`; require response property ID 1, bind that row by ID, and then create the administrator using the cloud property name. Do not run a name-based lookup or rewrite foreign-key references.

- [ ] **Step 3: Persist peer settings atomically**

Write a temporary environment file in the app data directory and replace `.env` only after registration and validation succeed. Set `SYNC_NODE_TYPE=lan_server`, `SYNC_PROPERTY_ID=1`, `SYNC_WORKER_STATUS_PATH` to the app-data `sync-worker-status.json`, and the peer JSON. Keep secrets out of the Laravel source tree and progress events. If persistence fails, report pairing failure and preserve the existing local `.env`.

- [ ] **Step 4: Add optional pairing fields and explicit result state to the wizard**

Show cloud URL and pairing code inputs together, communicate that cloud pairing is optional, disable submit while valid setup runs, and distinguish `local server ready`, `cloud linked`, and `cloud link failed; retry with a new code`. Pair before creating the first administrator when possible; on failure continue local setup and provide a retry action that calls `setup_retry_cloud` with a new code without rerunning migrations or recreating the administrator. On retry success, bind property ID 1 to the returned cloud property name before persisting the peer and starting the worker.

- [ ] **Step 5: Verify local-only and paired setup**

Run: `npm --prefix desktop run e2e:fast`

Run: `npm --prefix desktop run build`

Run: `php artisan test tests/Feature/Sync/BindCanonicalPropertyTest.php`

Expected: omitting cloud details still reaches local-server completion; valid details show linked only after successful persistence; invalid code is retryable and leaves local serving available.

- [ ] **Step 6: Commit server peer persistence and wizard**

```powershell
git add desktop/src-tauri/src/envfile.rs desktop/src-tauri/src/setup/mod.rs desktop/src-tauri/src/lib.rs desktop/src/main.ts desktop/e2e/specs-browser/wizard.spec.ts app/Console/Commands/BindCanonicalProperty.php app/Http/Controllers/Sync/NodeRegistrationController.php tests/Feature/Sync/BindCanonicalPropertyTest.php
git commit -m "feat: persist optional desktop cloud peer" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```

## Task 5: Supervise a dedicated sync worker

**Files:**
- Create: `desktop/src-tauri/src/sync_worker.rs`
- Modify: `desktop/src-tauri/src/lib.rs`
- Modify: `desktop/src-tauri/src/runtime.rs` only if a shared child-process primitive is needed.
- Test: Rust unit tests using a temporary fake executable/script.

**Interfaces:**
- `SyncWorker::start(php: PathBuf, app_root: PathBuf, env: Vec<(String, String)>, log_path: PathBuf, status_path: PathBuf, interval: Duration) -> Result<SyncWorker>`
- Runs the existing `php artisan sync:run` command once per interval only when `SYNC_ENABLED=true` and `SYNC_PEERS` is non-empty.
- `SyncWorker::stop()` joins the worker and terminates only its owned child; `Drop` calls `stop`.
- Writes the configured `sync-worker-status.json` atomically with `{ enabled, last_success_at, last_error, peers }`; each peer status includes its node ID and last result only. The app-data `.env` already passes its path to the Laravel web process as `SYNC_WORKER_STATUS_PATH`.

- [ ] **Step 1: Test worker gating, cycle invocation, restart, and stop**

The fake PHP executable records arguments and exits with controlled status. Assert no spawn when disabled/unconfigured; assert `artisan sync:run` is invoked when configured; assert a failed cycle is logged and a later cycle still runs; assert stop ends the owned worker.

- [ ] **Step 2: Implement one-cycle-at-a-time supervision**

Use the bundled PHP CLI and existing `app_root`; do not invoke `schedule:work` or `queue:work`. Serialize cycles so a slow process cannot overlap itself. Log timestamps, exit status, and bounded safe error text only.

- [ ] **Step 3: Retain and stop the worker with the server role**

Change managed active state to retain both server boot resources and the worker. Stop only owned worker/runtime/Postgres processes on app shutdown; preserve current tray-hide behavior when the server is active.

- [ ] **Step 4: Run focused Rust checks**

Run: `cargo test --manifest-path desktop/src-tauri/Cargo.toml`

Expected: worker tests pass; no default/release build includes test-only WebDriver plugins.

- [ ] **Step 5: Commit sync supervision**

```powershell
git add desktop/src-tauri/src/sync_worker.rs desktop/src-tauri/src/lib.rs desktop/src-tauri/src/runtime.rs
git commit -m "feat: supervise desktop server sync worker" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```

## Task 6: Expose pairing and sync status without revealing secrets

**Files:**
- Create: `app/Http/Controllers/Admin/SyncNodeStatusController.php`
- Create: `resources/js/Pages/Admin/Sync/Status.vue`
- Modify: `desktop/src-tauri/src/sync_worker.rs`
- Modify: `routes/admin.php`
- Modify: `resources/js/Components/Admin/AdminSidebar.vue`
- Test: `tests/Feature/Cloud/SyncStatusAuthorizationTest.php`.

**Interfaces:**
- Produces: authorized status includes peer ID/URL, last successful sync, last error, and linked/retry state; response excludes credential, pairing code, and hashes.

- [ ] **Step 1: Test response authorization and secret exclusion**

Assert `md`/`superuser` can access status, `manager` cannot, and recursively inspect JSON props to ensure no `credential`, `code_hash`, or plaintext code is exposed.

- [ ] **Step 2: Render stable status fields**

Read the path in `SYNC_WORKER_STATUS_PATH` and the existing sync-node/cursor data. Do not infer “linked” from a configured URL alone; require successful registration and persisted peer state. Display peer ID/URL, last success, and last error without credentials or hashes.

- [ ] **Step 3: Run focused Laravel and frontend checks**

Run: `php artisan test tests/Feature/Cloud/SyncStatusAuthorizationTest.php`

Run: `npm run build`

Expected: status is authorized, useful after reload, and contains no secrets.

- [ ] **Step 4: Commit status visibility**

```powershell
git add app/Http/Controllers/Admin/SyncNodeStatusController.php routes/admin.php resources/js/Pages/Admin/Sync/Status.vue resources/js/Components/Admin/AdminSidebar.vue tests/Feature/Cloud/SyncStatusAuthorizationTest.php
git commit -m "feat: expose safe desktop sync status" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```

## Task 7: Prove cloud pairing and sync with isolated UIs

**Files:**
- Create: `desktop/e2e/specs/cloud-pairing-walkthrough.spec.ts`
- Create: `desktop/e2e/cloud-pairing-admin-ui.py` using the installed Playwright Python environment for the actual Laravel admin page.
- Create: `desktop/e2e/run-cloud-pairing-walkthrough.ps1` to own the isolated Laravel process/database and pass the one-time code to the native test without printing it.
- Create: `database/seeders/WalkthroughCloudSeeder.php` for disposable fixture accounts and property.

- [ ] **Step 1: Start isolated cloud services and create fixture accounts**

Use a fresh SQLite file in a unique temporary directory and local HTTP URL; do not reuse developer or production data. Set `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE` to that file, and `APP_URL` to the loopback URL. Run `php artisan migrate --force` and `php artisan db:seed --class=WalkthroughCloudSeeder --force`. The seeder creates `md`, `superuser`, and `manager` accounts and one property with ID 1. Export `HOTELMS_E2E_CLOUD_URL`, `HOTELMS_E2E_MD_EMAIL`, `HOTELMS_E2E_MD_PASSWORD`, `HOTELMS_E2E_MANAGER_EMAIL`, `HOTELMS_E2E_MANAGER_PASSWORD`, and `HOTELMS_E2E_SCREENSHOT` from those isolated fixtures. Start the Laravel process on the selected local port and retain its exact PID for cleanup.

- [ ] **Step 2: Exercise the real admin UI**

Run the Python Playwright script against `HOTELMS_E2E_CLOUD_URL`. Log in as `manager` and verify 403; log in as `md`, select `lan_server`, create a code, retain it only in process memory, capture a screenshot, reload, and assert the one-time result element is absent. Serialize the code to captured stdout for the parent process only; the PowerShell runner must not forward it to the console or a log file.

```python
import json
import os
from playwright.sync_api import sync_playwright

cloud_url = os.environ["HOTELMS_E2E_CLOUD_URL"]
with sync_playwright() as playwright:
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()
    page.goto(f"{cloud_url}/login")
    page.wait_for_load_state("networkidle")
    page.locator('input[name="email"]').fill(os.environ["HOTELMS_E2E_MD_EMAIL"])
    page.locator('input[name="password"]').fill(os.environ["HOTELMS_E2E_MD_PASSWORD"])
    page.get_by_role("button", name="Log in").click()
    page.wait_for_load_state("networkidle")
    page.goto(f"{cloud_url}/admin/sync/pairing-codes")
    page.locator('[data-testid="sync-pairing-node-type"]').select_option("lan_server")
    page.get_by_role("button", name="Create pairing code").click()
    page.wait_for_selector('[data-testid="sync-pairing-code"]')
    pairing_code = page.locator('[data-testid="sync-pairing-code"]').inner_text()
    page.screenshot(
        path=os.environ["HOTELMS_E2E_SCREENSHOT"],
        full_page=True,
        mask=[page.locator('[data-testid="sync-pairing-code"]')],
    )
    page.reload()
    assert page.locator('[data-testid="sync-pairing-code"]').count() == 0
    browser.close()
print(json.dumps({"pairing_code": pairing_code}))
```

Use a separate browser context and the manager credentials to assert a 403. The runner captures the final JSON line in memory and supplies its code as `HOTELMS_E2E_PAIRING_CODE` to the native spec.

- [ ] **Step 3: Pair and verify the desktop server**

Read `HOTELMS_E2E_PAIRING_CODE` into test memory only and run native setup with the local cloud URL/code. Assert HTTP 201 registration, cloud `sync_nodes` type/property/node ID, persisted local peer, and local server `/up` plus authenticated `/login`. Never print the environment value or include it in failure output. Repeat with invalid/expired code and verify setup remains local-ready but unlinked.

- [ ] **Step 4: Verify sync and error visibility**

Create one syncable record on each isolated side, wait for the dedicated worker, and assert both database effects converge. Stop only the test cloud process, assert a visible peer error, restart it, and assert later cycles recover without duplicate records.

- [ ] **Step 5: Clean up and run all focused checks**

Run: `php artisan test tests/Feature/Sync/NodeRegistrationTest.php tests/Feature/Sync/NodePairingCommandsTest.php tests/Feature/Cloud/SyncPairingCodeAdminTest.php tests/Feature/Cloud/SyncStatusAuthorizationTest.php`

Run: `powershell.exe -NoProfile -ExecutionPolicy Bypass -File desktop\e2e\run-cloud-pairing-walkthrough.ps1`

The runner invokes the Playwright script and then the native spec with the captured code in process environment. Expected: real UI and isolated database assertions pass; test-owned services and data are cleaned up.

- [ ] **Step 6: Commit the isolated walkthrough**

```powershell
git add desktop/e2e/specs/cloud-pairing-walkthrough.spec.ts desktop/e2e/cloud-pairing-admin-ui.py desktop/e2e/run-cloud-pairing-walkthrough.ps1 database/seeders/WalkthroughCloudSeeder.php
git commit -m "test: verify isolated cloud pairing and server sync" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```
