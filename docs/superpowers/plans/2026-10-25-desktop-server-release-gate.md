# Desktop Server Release-Gate Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the isolated native desktop server walkthrough a trustworthy release gate that catches stale staging, exercises the real Tauri driver, and proves Laravel login without weakening CSRF.

**Architecture:** Stage the current Laravel tree and rebuild the E2E-feature binary before running native E2E. Fail early on migration-set mismatch, adapt the installed WebdriverIO v10 browser surface to the Tauri service, and capture safe HTTP/runtime diagnostics before correcting the reproduced login root cause.

**Tech Stack:** PowerShell, Node.js, WebdriverIO 10, Tauri 2/Rust, Laravel 12, FrankenPHP, embedded PostgreSQL.

**Spec:** `docs/superpowers/specs/2026-10-25-walkthrough-remediation-design.md`, sections “1. Desktop server gate and login diagnosis” and “Walkthrough and acceptance criteria”.

## Global Constraints

- All walkthrough databases, HTTP services, and credentials are isolated local/test resources.
- Never use a production or development database, live cloud deployment, or external payment service.
- Diagnose the `/login` failure at its reproduced root cause; do not bypass CSRF or suppress errors.
- Never log credential values, pairing codes, cookie values, or passwords.
- Firewall registration failure remains visible and actionable; do not auto-elevate or claim LAN access is open.
- The harness stops only processes it started and removes only its own test data.
- Preserve unrelated worktree changes.

---

## File Structure

- `scripts/stage-app.ps1` owns application staging and verifies migration parity.
- `desktop/package.json` owns the repeatable native E2E command.
- `desktop/e2e/wdio.conf.ts` owns native driver compatibility and preflight.
- `desktop/e2e/specs/server-setup.spec.ts` owns server wizard, filesystem/database assertions, HTTP health, and login proof.
- `desktop/e2e/specs/staff-client-walkthrough.spec.ts` owns the second-app staff-client workflow against an isolated running server.
- `desktop/e2e/helpers/client.ts` launches and cleans up only the test-owned second Tauri process with a separate app-data directory.
- The reproduced root cause may require a focused change in the Laravel middleware, session, or login surface; modify only the file proved responsible and cover it with an existing or newly added feature test.

## Task 1: Fail staging when migration sets differ

**Files:**
- Modify: `scripts/stage-app.ps1`
- Test: run the staging script and compare the source and staged migration names.

**Interfaces:**
- Produces: `stage-app.ps1` exits non-zero and prints the exact missing/extra filenames when `database/migrations` differs from `resources/app/database/migrations`.

- [ ] **Step 1: Add a migration parity check after staging**

Compare sorted `*.php` basenames from source and destination; do not compare only counts.

```powershell
$sourceMigrations = @(Get-ChildItem -LiteralPath (Join-Path $Repo "database\migrations") -Filter "*.php" -File |
    ForEach-Object { $_.Name } | Sort-Object)
$stagedMigrations = @(Get-ChildItem -LiteralPath (Join-Path $Dest "database\migrations") -Filter "*.php" -File |
    ForEach-Object { $_.Name } | Sort-Object)
$missing = @($sourceMigrations | Where-Object { $_ -notin $stagedMigrations })
$extra = @($stagedMigrations | Where-Object { $_ -notin $sourceMigrations })
if ($missing.Count -or $extra.Count) {
    throw "Migration staging mismatch. Missing: $($missing -join ', '); extra: $($extra -join ', ')"
}
Write-Host "Migration parity: $($sourceMigrations.Count) files"
```

- [ ] **Step 2: Run staging and inspect the parity result**

Run: `powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts\stage-app.ps1`

Expected: success prints the migration parity count and the source/staged sets match.

- [ ] **Step 3: Commit the staging guard**

```powershell
git add scripts/stage-app.ps1
git commit -m "fix: fail desktop staging on migration mismatch" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```

## Task 2: Make the native E2E command stage and rebuild its binary

**Files:**
- Modify: `desktop/package.json`
- Modify: `desktop/e2e/wdio.conf.ts`

**Interfaces:**
- Produces: `npm run e2e` builds the desktop frontend, stages the Laravel app, builds the Tauri binary with the `e2e` feature, checks migration parity, then invokes WebdriverIO against that binary.

- [ ] **Step 1: Add the preparation sequence to the native E2E script**

Keep `e2e:fast` unchanged. In `e2e`, run the desktop `npm run build`, invoke the repository staging script from the desktop directory, build `src-tauri/Cargo.toml` with `--features e2e`, and only then run `wdio run e2e/wdio.conf.ts`. Use the same target profile and binary path configured by the WDIO Tauri capability; do not build a release binary with the test-only driver.

```json
"e2e": "npm run build && powershell.exe -NoProfile -ExecutionPolicy Bypass -File ..\\scripts\\stage-app.ps1 && cargo build --manifest-path src-tauri\\Cargo.toml --features e2e && wdio run e2e/wdio.conf.ts"
```

- [ ] **Step 2: Add a pre-launch parity assertion to WDIO**

In `onPrepare`, compare source and staged migration basenames and throw before the app launches if they differ. This guards direct `wdio` invocations that bypass the npm wrapper.

- [ ] **Step 3: Verify the command reaches the native spec**

Run: `npm --prefix desktop run e2e -- --spec ./e2e/specs/server-setup.spec.ts`

Expected: staging and Cargo build complete first, then the server spec starts. A mismatch exits before launching Tauri. Do not interpret later unrelated failures as a successful release gate.

- [ ] **Step 4: Commit the repeatable native gate**

```powershell
git add desktop/package.json desktop/e2e/wdio.conf.ts
git commit -m "test: stage and rebuild before native desktop walkthrough" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```

## Task 3: Restore the Tauri service execution API under WDIO v10

**Files:**
- Modify: `desktop/e2e/wdio.conf.ts`
- Test: native server spec, plus `npm run e2e:fast` for browser-mode regression.

**Interfaces:**
- Consumes: the installed WebdriverIO browser object and the `@wdio/tauri-service@1.5.0` calls to `browser.execute`/`browser.executeAsync`.
- Produces: the Tauri service can install its hooks without an undefined-method error, while the existing v10 `overwriteCommand` compatibility shim continues to work.

- [ ] **Step 1: Add a focused driver-surface assertion**

Before the Tauri service hook runs, bind `browser.execute` to the browser's native execution command. Prefer an existing `execute`; use `executeScript` only when that exact v10 method exists. Do not introduce a guessed command name:

```ts
const execute = Reflect.get(browser, "execute");
const executeScript = Reflect.get(browser, "executeScript");
const nativeExecute = typeof execute === "function" ? execute : executeScript;
if (typeof nativeExecute !== "function") {
  throw new Error("WDIO browser has no supported script execution command");
}
browser.execute = nativeExecute.bind(browser);
```

Verify the adapter returns a value and serializes arguments correctly. If neither method is available, pin a compatible `@wdio/tauri-service`/WebdriverIO combination rather than emulating a missing command. Keep the existing `overwriteCommand` boolean-to-options translation.

- [ ] **Step 2: Run both driver modes**

Run: `npm --prefix desktop run e2e -- --spec ./e2e/specs/server-setup.spec.ts`

Expected: no native-service “browser.execute is not a function” initialization error; wizard assertions execute through the native Tauri session.

Run: `npm --prefix desktop run e2e:fast`

Expected: browser-mode mocks and existing wizard tests remain passing.

- [ ] **Step 3: Commit the compatibility fix**

```powershell
git add desktop/e2e/wdio.conf.ts
git commit -m "fix: adapt tauri e2e service to webdriverio v10" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```

## Task 4: Capture safe evidence for the login failure

**Files:**
- Modify: `desktop/e2e/specs/server-setup.spec.ts`
- Modify only if diagnostics identify a runtime issue: the responsible Laravel config, middleware, controller, or view.

**Interfaces:**
- Produces: login failure output includes HTTP status, content type, response URL, cookie names only, a bounded/sanitized body excerpt, and relevant tails from the app/runtime logs.

- [ ] **Step 1: Record the complete safe GET `/login` evidence**

On failure, include status, URL, content type, cookie names, and a bounded response excerpt with token-like values removed. Never print `Set-Cookie` values, session contents, passwords, or CSRF values. Keep the current cookie jar and CSRF POST assertion intact.

```ts
const cookieNames = loginPage.headers
  .getSetCookie()
  .map((value) => value.split("=", 1)[0]);
```

Use the test's existing `logTails()` helper and limit diagnostics to a fixed number of lines/characters. Redact `Set-Cookie`, `Cookie`, `X-XSRF-TOKEN`, authorization headers, passwords, pairing codes, and credentials before printing any excerpt.

```ts
function redactDiagnostics(value: string): string {
  return value
    .replace(/(Set-Cookie|Cookie|Authorization|X-XSRF-TOKEN)\s*:\s*[^\r\n]+/gi, "$1: [REDACTED]")
    .replace(/(password|credential|pairing_code|_token|xsrf-token)["'\s:=]+[^<\s"']+/gi, "$1=[REDACTED]")
    .replace(/(<input\b[^>]*\bname=["']_token["'][^>]*\bvalue=["'])[^"']+(["'][^>]*>)/gi, "$1[REDACTED]$2")
    .slice(0, 2048);
}
```

Apply this sanitizer to the body excerpt and each log-tail line before formatting diagnostics. Keep the existing login test's cookie jar only in memory.

- [ ] **Step 2: Reproduce the login request and identify the responsible layer**

Run: `npm --prefix desktop run e2e -- --spec ./e2e/specs/server-setup.spec.ts`

Expected: the test provides enough evidence to distinguish a non-2xx response, missing cookie, invalid session, and CSRF rejection. Inspect the relevant FrankenPHP and Laravel logs before changing production behavior.

- [ ] **Step 3: Add a regression assertion for the reproduced cause**

Add or extend the narrowest Laravel feature test for the proven defect in the exact middleware, session, controller, or view identified in Step 2. Assert the login page response and required cookie/session contract; do not assert away CSRF middleware or accept an unauthenticated response.

Run the focused test file created or updated in this step with `php artisan test`.

Expected: the regression fails against the original cause and passes after the minimal correction.

- [ ] **Step 4: Repair only the proven cause and repeat the native probe**

Run: `npm --prefix desktop run e2e -- --spec ./e2e/specs/server-setup.spec.ts`

Expected: `/up` remains 200; GET `/login` returns its real form and session/XSRF cookies; POST `/login` with the matching isolated cookie/session and CSRF token authenticates the test administrator; no CSRF bypass is present.

- [ ] **Step 5: Commit the diagnosis and correction**

Stage only the diagnostic spec, the regression test, and the Laravel file proven responsible; commit them as `fix: diagnose desktop server login flow`. Do not stage broad directories or the known unrelated helper change.

## Task 5: Remove the stale migration-count oracle and keep firewall limits explicit

**Files:**
- Modify: `desktop/e2e/specs/server-setup.spec.ts`
- Modify: `desktop/src/main.ts` only if the current completion panel fails to expose the existing firewall failure.

**Interfaces:**
- Produces: the test verifies the migration names/count against the staged source rather than expecting exactly 177; it checks firewall outcome as a surfaced step, not as a required success on an unelevated machine.

- [ ] **Step 1: Replace the hard-coded migration assertion**

Read migration filenames from both the repository and staged app and compare sets. On mismatch, print missing/extra names and fail. Keep the database migration-table assertion, but assert it matches the expected source migration set.

- [ ] **Step 2: Verify unprivileged firewall behavior**

Run the native spec unelevated. Expected: server setup and local HTTP assertions can pass while `register_firewall` is either succeeded or visibly failed with an elevation reason; the test must not claim remote LAN reachability when registration failed.

- [ ] **Step 3: Commit the release-gate assertion**

```powershell
git add desktop/e2e/specs/server-setup.spec.ts desktop/src/main.ts
git commit -m "test: assert staged migrations and firewall status" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```

## Task 6: Verify the isolated server release gate

**Files:**
- Test: `desktop/e2e/specs/server-setup.spec.ts`
- Test: `desktop/e2e/wdio.conf.ts`

- [ ] **Step 1: Run the complete server setup walkthrough**

Run: `npm --prefix desktop run e2e -- --spec ./e2e/specs/server-setup.spec.ts`

Expected: source/staged parity passes; the rebuilt test binary starts; UI setup, PostgreSQL, all migrations, seeded property/admin, `/up`, real login, and cleanup pass. Firewall failure is explicitly reported if this shell is not elevated.

- [ ] **Step 2: Verify cleanup**

Expected: only processes started by this test have been stopped; isolated test data is removed; existing WebView profile and unrelated AppData are unchanged; ports started by the test are closed.

- [ ] **Step 3: Commit the verified release gate**

```powershell
git add desktop/e2e/specs/server-setup.spec.ts
git commit -m "test: verify isolated desktop server release gate" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```

## Task 7: Verify a separate staff-client app links to the server

**Files:**
- Create: `desktop/e2e/specs/staff-client-walkthrough.spec.ts`
- Create: `desktop/e2e/helpers/client.ts`
- Modify: `desktop/src-tauri/src/lib.rs` only behind `#[cfg(feature = "e2e")]` to permit the second process to use a unique test app-data directory.

**Interfaces:**
- The helper launches the same E2E-feature Tauri binary with a distinct app-data directory and returns an owned process handle with `stop()`.
- The staff-client app receives `serverUrl`, saves only the server URL, opens the server login page, and does not create a local database.

- [ ] **Step 1: Add an E2E-only isolated app-data override**

Resolve the test-only data directory from `HOTELMS_E2E_DATA_DIR` only in `e2e` builds. Keep production app-data resolution unchanged. Add Rust tests proving the override is ignored in non-E2E builds.

- [ ] **Step 2: Launch a second app without sharing server state**

In the new helper, create a unique temporary directory, start the second Tauri binary with that directory, and retain the exact child PID. Never terminate unrelated processes by executable name.

- [ ] **Step 3: Drive the real staff-client wizard**

With the isolated LAN server running, use the second app's real UI to choose `Connect to a server`, enter `http://127.0.0.1:8000`, and submit once. Assert the Tauri completion event reports the exact server URL and the external opener target is `/login`.

- [ ] **Step 4: Verify the linked server login and cleanup**

Use the existing login credentials held in test memory to load and authenticate against the actual server login page. Assert the authenticated admin destination loads, the client app created no local database, then stop the exact client PID and remove only its temporary app-data directory.

- [ ] **Step 5: Run and commit the separate-client walkthrough**

Run: `npm --prefix desktop run e2e -- --spec ./e2e/specs/staff-client-walkthrough.spec.ts`

Expected: second app connects to the existing isolated server, reaches the real authenticated web surface, and all app/test processes and data owned by the spec are cleaned up.

```powershell
git add desktop/e2e/specs/staff-client-walkthrough.spec.ts desktop/e2e/helpers/client.ts desktop/src-tauri/src/lib.rs
git commit -m "test: verify separate desktop staff client link" -m "Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
```
