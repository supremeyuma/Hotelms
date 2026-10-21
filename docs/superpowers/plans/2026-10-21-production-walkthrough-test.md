# Production E2E Test Plan — MooreLife (HotelMS)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a committed, repeatable e2e harness that walks every user-facing
workflow — **Playwright** for the web app, **WebdriverIO + `@wdio/tauri-service`**
for the desktop app — and proves the product works end to end before release.

**Architecture:** Two suites in one repo.

- **Web (`tests/e2e/`)** — Playwright drives Chromium against the Laravel app
  running on **SQLite with a fresh seed**, started once per run by the harness.
- **Desktop (`desktop/e2e/`)** — WebdriverIO drives the built Tauri binary through
  the **embedded WebDriver provider**. The suite triggers the *real* setup
  commands (`setup_run_server`, `setup_run_terminal`) and asserts the resulting
  filesystem, database, and HTTP state — bundled Postgres boots, FrankenPHP
  serves, the web app answers.

**Tech stack:** Playwright, WebdriverIO, `@wdio/tauri-service`,
`tauri-plugin-wdio-webdriver`, `tauri-plugin-wdio`, Vitest-free plain Node,
Laravel 12, SQLite, Tauri v2, Rust 2024.

**Spec/plan lineage:** extends `docs/superpowers/plans/2026-10-05-phase-8-cloud-deployment.md`
(exit criteria), `2026-10-19-reference-snapshot.md`, and the desktop wizard from
`2026-10-05-phase-6-tauri-desktop-and-setup-wizard.md`. Supersedes the manual
runbook draft `2026-10-21-production-walkthrough-test.md`.

---

## Why this shape (decisions already made)

| Decision | Choice | Consequence |
|---|---|---|
| Desktop e2e depth | **Full setup, real Postgres + FrankenPHP boot** | Slow per run (real `initdb`, real migrations); highest fidelity; needs serial execution |
| Web e2e target | **Local Laravel app on SQLite, fresh seed** | Fast, isolated, reproducible; independent of a desktop install |
| Harness ownership | **Committed to the repo** | Reviewable, re-runnable in CI, grows with the product |
| Desktop driver (gate) | **Embedded WebDriver provider, native mode** | No external `tauri-driver`/msedgedriver; drives the built binary |
| Desktop driver (fast loop) | **WDIO browser mode** — Vite + mocked IPC | No Rust build, no Postgres, seconds not minutes; renderer-only |
| Web browser | **Playwright Chromium** (already cached at `%LOCALAPPDATA%\ms-playwright`) | No download gate on this machine |

### Two desktop tiers, and which one gates the release

| Tier | Config | Runs | Speed | Authority |
|---|---|---|---|---|
| **Fast loop** | `desktop/e2e/wdio.browser.conf.ts`, `mode: 'browser'` | The wizard frontend in Chrome against `vite dev`, with `invoke()` mocked | Seconds | Development only — catches renderer/state/selector regressions |
| **Release gate** | `desktop/e2e/wdio.conf.ts`, native `embedded` provider | The built binary, real Postgres, real FrankenPHP | Minutes | The only run that can sign off a release |

The fast loop is the day-to-day loop; the native suite is the pre-release proof.
**A green fast loop never substitutes for the native run** — it proves the
renderer, not the product.

### What this harness can and cannot prove

**Can:** the wizard renders and reacts to real events; role selection is exclusive;
each setup step's real side effects happen (Postgres cluster created, migrations
applied, admin created, FrankenPHP answers `/up`); the terminal pairs and pulls a
snapshot; the web app's every workflow works on seeded data.

**Cannot (owner-run, unavoidable):** the shipped *installer wrapper* (NSIS/MSI),
SmartScreen behaviour, code signing, Coolify/cloud Postgres, live payment keys,
real thermal printers. The desktop suite drives a **built binary**, not the
installed artifact. This is stated plainly so no one reads a green run as more
than it is.

---

## Global Constraints

- **The web version must not change behaviour.** Phase 8 R2 still holds: adding
  the harness must never modify a screen, component, view, controller, or route.
  The harness lives in test directories and `desktop/e2e/` only.
- **`desktop/src-tauri/src/envfile.rs` and `postgres.rs` must never be
  reformatted** (standing rule).
- **`desktop/src-tauri/src/lib.rs` may be touched**, but only to register the two
  wdio plugins behind a non-release feature or env guard — see Task 2. Prefer
  gating over always-on so the shipped binary does not carry a WebDriver server.
- **Full PHP suite green before every commit.** Run `php artisan config:clear; php
  artisan test` (NOT `composer test` — it exceeds composer's 300 s timeout here).
- **Rust: `cargo test` must stay green** (currently 22 passed). `cargo clippy -- -D
  warnings` currently fails on 5 **pre-existing** `collapsible_if` sites
  (`discovery.rs:176`, `postgres.rs:176`, `runtime.rs:138`, `setup/mod.rs:750`,
  `lib.rs:440`). Do not "fix" these by editing protected files; leave the
  pre-existing clippy state exactly as found. **Adding plugins to `lib.rs` must
  not add a new warning** — verify with `cargo clippy 2>&1 | Select-String lib.rs`.
- **Never run `migrate:fresh` or any destructive migration against the dev MySQL
  `hotelms` database.** The harness must use its own SQLite file or a scratch
  database only.
- **No scratch files in the repo.** Test fixtures live under the suite directories.
- Commit after every task. Small, focused commits in the house style
  (`test: …`, `chore: …`, `feat: …`, `fix: …`).
- Pint on changed PHP only (it reformats unrelated drifted files otherwise).
  `php -l` after every PHP edit. `git commit -m` only (never `Out-File` — it adds a BOM).

---

## Preconditions and the one blocker that must land first

### The blocker (verified against the live dev database, not assumed)

Only **5 of 28** seeded accounts have Spatie roles that satisfy route middleware.
`ceo@email.com`, `manager@email.com`, `inventory@email.com` have **no roles at
all**, and the operational roles `manager`, `frontdesk`, `staff`, `kitchen`,
`bar`, `clean`, `laundry`, `inventory` **do not exist in the database**.

Root cause, confirmed in code:

- `DatabaseSeeder` runs `UsersSeeder`, which writes only the legacy `users.role_id`
  column and **never** populates Spatie's `model_has_roles` pivot. Route middleware
  reads the pivot, so those users are rejected.
- Worse, `UsersSeeder` resolves slugs `md`, `manager`, `inventory_manager`,
  `staff`, `guest` against `roles`, but `RolesSeeder` inserts **display-name**
  slugs (`super-admin`, `hotel-manager`, `inventory-manager`, …). The lookup
  returns `null`, so even `role_id` is empty.
- Only `SuperUserSeeder`, `HrUserSeeder`, `AccountantUserSeeder`,
  `ClubRolesAndPermissionsSeeder`, `ClubDrinkSeeder` and `LocalHotelDemoSeeder`
  populate the Spatie pivot correctly — and `DatabaseSeeder` calls none of the
  demo ones.

**Every web scenario depends on this being fixed.** It is Task 0 and it blocks
everything else.

---

## File Structure

**Created — web suite:**
- `playwright.config.ts` — Playwright config, one Chromium project, webServer hook.
- `tests/e2e/` — Playwright specs (see task list for the file breakdown).
- `tests/e2e/support/` — shared helpers: login, seeding, selectors, fixtures.
- `tests/e2e/.gitignore` — ignore `test-results/`, `playwright-report/`.

**Created — desktop suite:**
- `desktop/e2e/wdio.conf.ts` — WebdriverIO config, `@wdio/tauri-service`.
- `desktop/e2e/specs/` — WDIO specs.
- `desktop/e2e/helpers/` — app-data-dir isolation, state reset, log capture.

**Modified:**
- `desktop/src-tauri/Cargo.toml` — add `tauri-plugin-wdio-webdriver`, `tauri-plugin-wdio` (optional/feature-gated).
- `desktop/src-tauri/src/lib.rs` — register the plugins in the builder (feature-gated).
- `desktop/package.json` — e2e scripts + devDeps.
- `package.json` — `test:e2e` script.
- `DATABASE Seeders` — see Task 0 (only application change in this plan).

**Responsibility note:** test infrastructure plus one seeding correctness fix. No
production behaviour change beyond making role seeding correct.

---

## Task 0: Make every seeded account log in (the blocker) — DONE

**Status: complete.** Commits `b6b22ebb` (fix) and `9ead9aad` (phpunit memory limit).

**Files (as actually changed):**
- Modify: `database/seeders/RolesSeeder.php` — rewritten to the operational set
- Modify: `database/seeders/UsersSeeder.php` — rewritten; writes both role stores
- Modify: `database/seeders/StaffProfilesSeeder.php` — uses the `StaffProfile` model
- Modify: `database/seeders/AccountantUserSeeder.php` — role name converged to `accountant`
- Modify: `phpunit.xml` — `memory_limit=512M`; the 457-test suite exceeded CLI 128M
- Test: `tests/Feature/Auth/SeededAccountLoginTest.php` (new)

**Two details differed from the plan as written:**
1. `DatabaseSeeder` was **not** modified. The operational accounts were added to
   `UsersSeeder` instead. User decision: a fresh install reaches every dashboard
   without also running the optional `LocalHotelDemoSeeder`.
2. The legacy `users.role_id` column **is still written**, alongside the Spatie
   pivot. `BookingsSeeder`, `OrdersSeeder` and `StaffProfilesSeeder` join on
   `roles.slug` through it; dropping it would have silently emptied them.

**Adjacent bugs the fix unmasked** (both pre-existing, both invisible before
because the lookups they depend on matched nothing):
- `StaffProfilesSeeder` inserted `action_code_hash`, renamed to `action_code` by
  `2025_12_28_032826_rename_action_code_hash_column`. Now uses
  `StaffProfile::storeActionCode()`, which detects the column itself.
- `StaffProfilesSeeder` also targeted emails (`*@lagosharborhotel.com`) from a
  different seeder that produces no such accounts. Dead blocks removed.
- `AccountantUserSeeder` created a second role named `Accountant` (capital A)
  with the same slug as `accountant`, colliding on `roles.slug`.

**Pre-existing bug found, NOT fixed (out of scope, unrelated to login):**
`LocalHotelDemoSeeder` fails with `maintenance_tickets has no column named
booking_id`. Reproduced on a clean tree with this task's changes stashed, so it
predates Task 0. It seeds a self-contained demo hotel and is not on the
`db:seed` path. Left alone deliberately — fix before relying on that seeder.

- [x] **Step 1: Write the failing test first**

Create `tests/Feature/Auth/SeededAccountLoginTest.php`. It should run the seeders
and assert, for each account in the login matrix, that the user exists, has a
Spatie role, and can reach its landing route. The committed version uses PHPUnit
class style (not Pest) to match the surrounding suite, asserts the seeded password
hash really verifies, and adds a case proving the legacy `role_id` column stays
populated for the three seeders that read it.

```php
it('gives every seeded account a role that its landing route accepts', function () {
    $this->seed();

    $matrix = [
        ['superuser@hotelms.com', 'admin.dashboard'],
        ['ceo@email.com', 'admin.dashboard'],
        ['manager@email.com', 'admin.dashboard'],
        ['frontdesk@email.com', 'frontdesk.dashboard'],
        ['kitchen@email.com', 'staff.kitchen.dashboard'],
        ['bar@email.com', 'staff.bar.dashboard'],
        ['clean@email.com', 'clean.dashboard'],
        ['laundry@email.com', 'staff.laundry.dashboard'],
        ['hr@email.com', 'hr.staff.index'],
        ['account@email.com', 'finance.dashboard'],
    ];

    foreach ($matrix as [$email, $route]) {
        $user = \App\Models\User::where('email', $email)->firstOrFail();
        expect($user->roles)->not->toBeEmpty("{$email} has no role");

        $this->actingAs($user)->get(route($route))->assertOk();
    }
});
```

- [x] **Step 2: Run it and confirm RED**

Run: `php artisan config:clear; php artisan test --filter=SeededAccountLoginTest`
Expected: FAIL — accounts have no roles / routes 403. Confirmed: all 4 tests failed.

- [ ] **Step 3: Fix `RolesSeeder` to define the operational role set**

Replace the display-name role definitions with the names the route middleware
actually checks: `superuser`, `md`, `manager`, `frontdesk`, `staff`, `kitchen`,
`bar`, `clean`, `laundry`, `inventory`, `accountant`, `hr`, `guest`. Keep
`slug` populated (it is `NOT NULL` on every driver; see `CreateAdminCommand` for
the pattern).

- [ ] **Step 4: Make the account seeder populate the Spatie pivot**

In `UsersSeeder` (or the new `DemoAccountsSeeder`), after creating each user call
`$user->syncRoles([...])` with the operational role name. Decide the fate of the
legacy `users.role_id` write: **check `BookingsSeeder`, `OrdersSeeder`,
`StaffProfilesSeeder` first** — they query `users.role_id` and will break if it
stops being written before they are updated to use roles.

- [ ] **Step 5: Reconcile the demo/demo-user duplication**

`LocalHotelDemoSeeder` and `UsersSeeder` define overlapping accounts today. Pick
one as authoritative for an installation and document the choice in the seeder's
docblock. Do not leave two sources of truth.

- [x] **Step 6: Run the test until GREEN**

Run: `php artisan config:clear; php artisan test --filter=SeededAccountLoginTest`
Result: 4 passed (59 assertions).

- [x] **Step 7: Verify no other seeders broke**

Run: `php artisan config:clear; php artisan test`
Result: **461 passed / 2722 assertions** (previous baseline 457 + 4 new), no
regressions. Also verified against a scratch SQLite DB that `db:seed` produces 15
operational roles, 35 role assignments, 12 bookings and 15 staff profiles.

- [x] **Step 8: Commit**

```
git commit -m "fix: seed operational roles so every seeded account can log in"
```

---

## Task 1: Playwright harness skeleton and app-under-test

**Files:**
- Create: `playwright.config.ts`
- Create: `tests/e2e/support/app.ts`
- Create: `tests/e2e/.gitignore`
- Modify: `package.json` (devDep `@playwright/test`, script `test:e2e`)

- [ ] **Step 1: Install Playwright**

```
npm install --save-dev @playwright/test
```
Browsers are already cached at `%LOCALAPPDATA%\ms-playwright`, but run
`npx playwright install chromium` once to be certain.

- [ ] **Step 2: Write `playwright.config.ts`**

Requirements:
- `testDir: './tests/e2e'`.
- One project: `chromium` (desktop viewport), plus a `mobile` project for the
  guest-surface scenarios at a phone viewport.
- `webServer`: start the Laravel app on a dedicated port with a **dedicated
  SQLite file** and a fresh seed. Use an env override so `.env` is untouched:

```ts
webServer: {
  command: 'php artisan migrate --force && php artisan db:seed --force && php artisan serve --port=8123',
  url: 'http://127.0.0.1:8123/up',
  reuseExistingServer: !process.env.CI,
  env: {
    APP_ENV: 'e2e',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: 'database/e2e.sqlite',   // NOT the dev hotelms MySQL
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'log',
  },
},
```

- [ ] **Step 3: Add `database/e2e.sqlite` to `.gitignore`**

Also add `storage/logs/*.log` rotation awareness: the auth and booking specs read
the log, so each run should start with a cleared log. Do that in the webServer
`command` (`> storage/logs/laravel.log` first) so specs never read a stale run.

`phpunit.xml` only scans `tests/Unit` and `tests/Feature`, so `tests/e2e/` cannot
be picked up by `php artisan test` — confirm this stays true if the suites are
ever renamed.

Config caching is the classic footgun here: run `php artisan config:clear` before
the e2e server starts, or the `DB_CONNECTION`/`DB_DATABASE` overrides in the
`webServer.env` block will be ignored and the suite will run against the dev MySQL
`hotelms` database. That is the one mistake that could damage dev data — make it
impossible.

- [ ] **Step 4: Write `tests/e2e/support/app.ts`**

Helpers: `login(page, email, password)`, `logout(page)`, `expectLanding(page,
route)`, and a `resetMailLog()`/`readMailLog()` pair that reads
`storage/logs/laravel.log` for confirmation emails.

- [ ] **Step 5: Smoke test**

Create `tests/e2e/smoke.spec.ts`: navigate to `/`, assert the public home renders
and the response is 200. Run `npx playwright test smoke`.

- [ ] **Step 6: Commit**

```
git commit -m "test: add the Playwright harness and web app-under-test"
```

---

## Task 2: Desktop e2e harness skeleton and plugin wiring

**Files:**
- Modify: `desktop/src-tauri/Cargo.toml`
- Modify: `desktop/src-tauri/src/lib.rs`
- Create: `desktop/e2e/wdio.conf.ts`
- Create: `desktop/e2e/helpers/appdata.ts`
- Modify: `desktop/package.json`

- [ ] **Step 1: Add the plugins, feature-gated**

In `desktop/src-tauri/Cargo.toml`:

```toml
[dependencies]
tauri-plugin-wdio-webdriver = { version = "1", optional = true }
tauri-plugin-wdio = { version = "1", optional = true }

[features]
e2e = ["tauri-plugin-wdio-webdriver", "tauri-plugin-wdio"]
```

In `lib.rs`, register them only under `#[cfg(feature = "e2e")]` so **the release
binary never carries a WebDriver server**. Add the conditional builder step next
to the existing `.plugin(tauri_plugin_opener::init())`.

- [ ] **Step 2: Confirm the guard holds both ways**

```
cargo build --release --manifest-path desktop\src-tauri\Cargo.toml           # no wdio in the binary
cargo build --features e2e --manifest-path desktop\src-tauri\Cargo.toml     # wdio available
cargo test  --manifest-path desktop\src-tauri\Cargo.toml                    # 22 still pass
```

- [ ] **Step 3: Install the WDIO toolchain**

```
npm --prefix desktop install --save-dev @wdio/tauri-service @wdio/cli @wdio/local-runner @wdio/mocha-framework @wdio/spec-reporter
```

- [ ] **Step 4: Write `desktop/e2e/wdio.conf.ts`**

```ts
export const config = {
  runner: 'local',
  specs: ['./specs/**/*.spec.ts'],
  maxInstances: 1,                       // real Postgres boot; never parallel
  services: [['tauri', {
    appBinaryPath: '<repo>/desktop/src-tauri/target/debug/desktop',
    driverProvider: 'embedded',
  }]],
  framework: 'mocha',
  mochaOpts: { timeout: 600000 },        // initdb + migrate are minutes, not seconds
  capabilities: [{ 'tauri:options': { application: '<same path>' } }],
};
```

- [ ] **Step 5: Solve state isolation (this is the important one)**

`boot()` in `main.ts` resumes the persisted role when
`setup-state.json` says `running`, and there is **no `reset` IPC command**
(confirmed: `invoke_handler` registers exactly 9 commands, none of them `reset`).
So each run must start from a clean app-data dir. In `helpers/appdata.ts`, locate
the app data dir (identifier `com.moorelife.desktop`), delete
`setup-state.json` and the generated DB/env before each spec, and document this.
If a cleaner path is preferred, add an `e2e`-gated `setup_reset` command — but the
directory wipe needs no production code, so prefer it.

- [ ] **Step 6: Smoke test — the app boots and shows the role screen**

Create `desktop/e2e/specs/smoke.spec.ts`: assert `[data-role="server"]`,
`[data-role="terminal"]`, `[data-role="staff_client"]` are all present and the
heading reads "How will this PC be used?".

- [ ] **Step 7: Commit**

```
git commit -m "test: add the desktop e2e harness with a feature-gated WebDriver plugin"
```

---

## Task 2b: Desktop fast loop — WDIO browser mode

**Files:**
- Create: `desktop/e2e/wdio.browser.conf.ts`
- Create: `desktop/e2e/specs-browser/wizard.spec.ts`
- Create: `desktop/e2e/specs-browser/mocks.ts`
- Modify: `desktop/package.json` (script `e2e:fast`)

This tier runs the wizard frontend in **plain Chrome** against `vite dev`, with
`window.__TAURI_INTERNALS__.invoke` patched so `invoke()` is intercepted in the
renderer. No Rust build, no Postgres, no driver. It catches renderer, state, and
selector regressions in seconds.

- [ ] **Step 1: Write `desktop/e2e/wdio.browser.conf.ts`**

```ts
export const config = {
  runner: 'local',
  specs: ['./specs-browser/**/*.spec.ts'],
  maxInstances: 1,
  services: [['@wdio/tauri-service', {
    mode: 'browser',
    devServerUrl: 'http://localhost:1420',
    devServer: { command: 'npm --prefix desktop run dev', reuseExistingServer: true },
  }]],
  capabilities: [{ browserName: 'tauri' }],
  framework: 'mocha',
  mochaOpts: { timeout: 20000 },
};
```

Note the service name is the full package `'@wdio/tauri-service'`, and `mode` /
`devServerUrl` may sit at the service level so capabilities inherit them. Letting
the service auto-manage `devServer` is preferred — it waits for readiness and
tears the tree down afterwards.

- [ ] **Step 2: Register mocks before the app boots (the timing gotcha)**

The injection script runs **after** `browser.url()` resolves, so `invoke()` calls
made during module init are **not** intercepted. `main.ts` calls `listen()`,
`setup_status` and (after a role click) `setup_plan` early — so a spec that
navigates and immediately asserts will hit
`"unmocked Tauri command in browser mode: setup_status"`.

Handle it in `specs-browser/mocks.ts`:
- Define a `registerWizardMocks(browser, { completed = [], running = false })`
  helper that mocks `setup_status`, `setup_plan`, `discovery_pairing` and
  `discovery_probe` with sensible defaults.
- Call `browser.url(...)` **first**, then register the mocks, then reload
  (`browser.url(...)` again) so `boot()` sees the mocks. Document this ordering in
  a comment — it is the single most likely source of confusion for the next person.
- Because `browser.tauri.mock(cmd)` **fully resets** the mock each call, set
  implementations in `beforeEach`, never `beforeAll`.

- [ ] **Step 3: Write the specs**

Cover the renderer paths that do not need a real backend:
- Role select: three cards present; heading text; clicking a card renders that
  role's form heading and submit label from `ROLE_DEFS`.
- Step list: mock `setup_plan` to return fixed steps and assert `[data-step="<id>"]`
  rows render in order with pending badges.
- **Event handling:** `browser.tauri.emitEvent('setup://step', {...})` drives
  `applyStep` — assert badges flip to `done`/`failed` and `.step__message`
  populates (including the `Cause:` prefix on failure).
- **Done panel:** emit `setup://done` with `ok: true` for role `server`; assert the
  heading, `[data-open]`, and that `.pairing__row` has a `canvas` + `code`
  containing `moorelife://pair` (mock `discovery_pairing`).
- **Failure panel:** emit `setup://done` with `ok: false`; assert `[data-retry]`
  and the message.
- **Double-submit guard:** submit twice fast; assert a single `invoke` and that
  the button gained `data-disabled` immediately.
- **IPC arguments:** assert `setup_run_server` is invoked with exactly the typed
  form values (`adminName`, `adminEmail`, `adminPassword`, `propertyName`), and
  that a blank property name sends `null` (see `main.ts`).

- [ ] **Step 4: Wire the script**

`desktop/package.json`: `"e2e:fast": "wdio run e2e/wdio.browser.conf.ts"`.

- [ ] **Step 5: Run it**

```
npm --prefix desktop run e2e:fast
```
Expected: green in seconds. Then confirm the native gate still works:
`npm --prefix desktop run e2e`.

- [ ] **Step 6: Commit**

```
git commit -m "test: add the desktop browser-mode fast loop with mocked IPC"
```

---

## Task 3: Desktop — server role, real boot

**Files:**
- Create: `desktop/e2e/specs/server-setup.spec.ts`

Selectors are stable and verified in `desktop/src/main.ts`:
role cards `[data-role=…]`; fields `#admin-name`, `#admin-email`,
`#admin-password`, `#property-name`; steps `[data-step="<id>"]` with
`.step__badge` / `.step__message`; failure panel `[data-retry]`; done panel
`[data-open]`; pairing `.pairing__row`. Events are `setup://step`, `setup://done`.

- [ ] **Step 1: Write the spec**

- Click `[data-role="server"]`.
- Assert the nine steps render in order: `unpack_postgres`, `generate_env`,
  `migrate`, `seed`, `create_admin`, `bind_network`, `register_firewall`,
  `register_cloud`, `install_tray`.
- Fill the form and submit.
- **Assert each step's badge reaches a terminal state** and capture every
  `.step__message`, especially for `register_firewall` (non-Windows: skipped) and
  `register_cloud` (always "not configured yet"). Assert those two against their
  **documented** behaviour — see Known Gaps.
- On the done panel: `[data-open]` present, `.pairing__row` count ≥ 1, each row
  has a `canvas` and a `code` containing `moorelife://pair`.

- [ ] **Step 2: Assert the real side effects (the point of this task)**

Not just the UI — assert the machine changed:
- The Postgres data dir exists under the app data dir and `PG_VERSION` is present.
- `hotelms.sqlite` / the cluster's `hotelms` database exists with migrated tables.
- The admin account from the form exists in the seeded database.
- **FrankenPHP answers:** `fetch('http://127.0.0.1:8000/up')` returns 200.
- Log in over HTTP with the admin credentials created in the wizard → 200.

Capture, on failure, the runtime log tail and the Postgres log tail
(`tail_log` output is available via the wizard's logs on disk).

- [ ] **Step 3: Run it**

```
npm --prefix desktop run e2e -- --spec desktop/e2e/specs/server-setup.spec.ts
```
Expected: PASS on a clean machine. First run is slow (untar + initdb + migrate).

- [ ] **Step 4: Commit**

```
git commit -m "test: prove the server role really boots Postgres and FrankenPHP"
```

---

## Task 4: Desktop — terminal and staff client roles

**Files:**
- Create: `desktop/e2e/specs/terminal-setup.spec.ts`
- Create: `desktop/e2e/specs/staff-client.spec.ts`

**This task depends on Task 3**: the terminal pairs against a running server, so
the suite must start the server first (use a Mocha `before` hook that runs the
server spec's setup as a prerequisite, or run specs in a fixed order with
`maxInstances: 1` — do not parallelise).

- [ ] **Step 1: Server prerequisite helper**

Extract the Task 3 server boot into `helpers/server.ts` so both suites share it.
Do not duplicate the wizard walk.

- [ ] **Step 2: Terminal spec**

- Click `[data-role="terminal"]`; assert the four steps: `create_database`,
  `migrate`, `register_node`, `pull_snapshot`.
- Fill `#property-code` and `#pairing-code` from the server's pairing panel.
- Assert `pull_snapshot`'s `.step__message` contains the pulled count.
- Assert real side effects: a SQLite file exists, has the reference tables, and
  the seeded accounts are present — i.e. **log in on the terminal**.
- Re-run the pull and assert idempotency (no duplicate rows). Use
  `php artisan sync:refresh-reference` against the terminal's DB and compare
  counts before/after.

- [ ] **Step 3: Staff-client spec**

- Click `[data-role="staff_client"]`, enter the server URL, assert `open_window`
  succeeds and `discover_server` reports **Skipped** with its documented message
  (Known Gaps). Assert the client has **no** local database.

- [ ] **Step 4: Run and commit**

```
npm --prefix desktop run e2e
git commit -m "test: prove terminal pairing, snapshot pull, and the staff client"
```

---

## Task 5: Web — authentication and account lifecycle

**Files:**
- Create: `tests/e2e/auth.spec.ts`

- [ ] **Step 1: Write the spec**

`/login`, wrong password, correct login for every account in the Task 0 matrix
with its expected landing route, `/register`, forgot-password (assert against the
log mailer), password confirmation, `/profile` update + password change + delete
on a throwaway account, logout, and deep-link-while-logged-out (after login you
must land on the deep link, not the generic dashboard).

- [ ] **Step 2: Run, then commit**

```
npx playwright test auth
git commit -m "test: cover authentication and the account lifecycle end to end"
```

---

## Task 6: Web — public site, booking wizard, payments

**Files:**
- Create: `tests/e2e/public-site.spec.ts`
- Create: `tests/e2e/booking.spec.ts`

- [ ] **Step 1: Public site**

`/`, `/gallery`, `/amenities`, `/club-lounge`, `/policies` render with images
loaded (assert `naturalWidth > 0`, not just presence). Contact + feedback forms
submit and appear in admin. Public menus render for the seeded menu types.

- [ ] **Step 2: Booking wizard**

Full pass: search → rooms → select → guest details → review → discount apply /
remove → create → payment → confirmation.

**The gateway must be stubbed at the server, not the browser.** The PHPUnit trait
`tests/Concerns/FakesPaymentGatewayHttp.php` fakes outgoing HTTP with `Http::fake`,
which a Playwright run against a real server **cannot** do. The supported lever is
the env-overridable base URL in `config/payment.php`:

- `FLUTTERWAVE_BASE_URL` (default `https://api.flutterwave.com/v3`)
- `PAYSTACK_BASE_URL` (default `https://api.paystack.co`)

Point both at a local stub server the harness starts (a few routes that return a
checkout URL, then a verification payload), and stub the webhook endpoints with
signed payloads. Set the corresponding `*_SECRET_KEY` values to test constants.
Record this wiring in `docs/E2E_TESTING.md` and keep the stub inside
`tests/e2e/support/` — never a real gateway from CI, and never live keys.

Negative cases: no availability → empty state; double submit → one booking;
forged cancel signature → rejected.

- [ ] **Step 3: Run, then commit**

```
npx playwright test public-site booking
git commit -m "test: cover the public site and the booking wizard end to end"
```

---

## Task 7: Web — front desk, kitchen/bar, housekeeping/laundry/maintenance

**Files:**
- Create: `tests/e2e/front-desk.spec.ts`
- Create: `tests/e2e/kitchen-bar.spec.ts`
- Create: `tests/e2e/back-of-house.spec.ts`

- [ ] **Step 1: Front desk** — check-in the booking from Task 6, extend stay,
  billing (add charge, take payment, balance updates), room move, check-out,
  guest request acknowledge→complete, laundry at desk (assert the print
  **dialog path is invoked**, not real paper — see Known Gaps).

- [ ] **Step 2: Kitchen/bar** — place a room-service order, watch it reach the
  queue, advance statuses to ready, mark payment, then full menu CRUD
  (category → subcategory → item → image → toggle → reorder → delete), verifying
  each change on the public menu from Task 6.

- [ ] **Step 3: Back of house** — cleaning dashboard + mark cleaned; cleaning
  templates CRUD; laundry order lifecycle; maintenance ticket lifecycle across
  the staff and admin views (both must agree).

- [ ] **Step 4: Run, then commit**

```
npx playwright test front-desk kitchen-bar back-of-house
git commit -m "test: cover front desk, kitchen/bar, and back-of-house workflows"
```

---

## Task 8: Web — admin, reports, finance, HR, Club POS, inventory

**Files:**
- Create: `tests/e2e/admin-core.spec.ts`
- Create: `tests/e2e/reports.spec.ts`
- Create: `tests/e2e/finance-hr.spec.ts`
- Create: `tests/e2e/club-pos.spec.ts`
- Create: `tests/e2e/inventory.spec.ts`

- [ ] **Step 1: Admin core** — rooms CRUD + the full QR cycle (generate →
  regenerate → show → download → invalidate); room types; room scheduling
  (availability, price schedules, bulk update); bookings admin (edit,
  price-override approve **and** reject, charges, payments, delete); discount
  codes; staff admin (create, edit, suspend, reinstate, delete, note, threads);
  settings + test-mail; website CMS content + gallery; events admin; feedback admin.

- [ ] **Step 2: Reports** — every `admin/reports.*` route renders with **non-empty
  data** (assert a known cell, not just HTTP 200 — a 200-on-empty is how report
  bugs hide). All three export formats download and are non-empty. **Assert the
  summary cards are links to the filtered destination** — the house UX standard
  (§ "Summary cards must be clickable"); this is a real spec, so test it.

- [ ] **Step 3: Finance + HR** — finance dashboard; revenue/P&L/balance-sheet/
  daily-revenue + CSV exports; outstanding balances + export; accounting periods
  (store, initialize tax accounts, check status, close, reopen); audit log shows
  the actions just performed. HR dashboard redirect; HR staff CRUD + threads.

- [ ] **Step 4: Club POS** — dashboard for a `club_staff` account; a non-POS role
  is **denied**; PIN switch + throttle; docket open → items → pay → reprint →
  void → attach to booking; shift open/close/reconcile; stock + reports; the
  offline/connectivity banner toggling with network state.

- [ ] **Step 5: Inventory** — item CRUD, use/transfer/reconcile/add-stock,
  locations CRUD, menu recipes, low-stock threshold behaviour.

- [ ] **Step 6: Run, then commit**

```
npx playwright test admin-core reports finance-hr club-pos inventory
git commit -m "test: cover admin, reports, finance, HR, Club POS, and inventory"
```

---

## Task 9: Web — guest in-room surface

**Files:**
- Create: `tests/e2e/guest-room.spec.ts` (runs in the `mobile` project)

- [ ] **Step 1: Write the spec**

Generate a room QR in admin, open `/guest/room-entry/{qr_key}` at a phone
viewport, then: order room service (reaches the kitchen queue), service request
(reaches front desk), maintenance (reaches admin), extend stay, checkout request,
payment, bill history, outstanding bill, laundry request, order history + cancel,
feedback. **Security assertions are mandatory:** a tampered token is rejected, and
a token for another room is rejected.

- [ ] **Step 2: Run, then commit**

```
npx playwright test guest-room
git commit -m "test: cover the guest in-room QR surface end to end"
```

---

## Task 10: Cross-cutting assertions

**Files:**
- Create: `tests/e2e/cross-cutting.spec.ts`

- [ ] **Step 1: Write the spec**

- **Double-submit protection** (house standard): rapid double-click on each
  primary form's submit creates exactly one record and the button enters a
  disabled/loading state immediately.
- **Loading feedback** present on navigation and async actions.
- **Authorization:** for each role, request a URL belonging to another role →
  denied, not leaked.
- **Error states:** bad input on each major form → friendly error, never a raw
  500 or stack trace.
- **Empty states:** a list with no rows renders an intentional empty state.
- **Sync cannot break the web** (Phase 8 constraint): with a peer stopped or a
  malformed change present, web requests still succeed.

- [ ] **Step 2: Run, then commit**

```
npx playwright test cross-cutting
git commit -m "test: assert the cross-cutting UX and resilience standards"
```

---

## Task 11: Wire scripts, docs, and a single entry point

**Files:**
- Modify: `package.json`, `desktop/package.json`
- Create: `docs/E2E_TESTING.md`

- [ ] **Step 1: Scripts**

Root: `"test:e2e": "playwright test"`.
Desktop: `"e2e": "wdio run e2e/wdio.conf.ts"` (release gate) and
`"e2e:fast": "wdio run e2e/wdio.browser.conf.ts"` (fast loop).

- [ ] **Step 2: `docs/E2E_TESTING.md`**

Document: what each suite covers, how to run each, how to add a spec, the
app-data isolation requirement, the slow-first-run expectation for the native
desktop suite, and — in a prominent table — **which tier gates a release**
(native + Playwright) versus which is the development loop (browser mode).
Also record the browser-mode ordering constraint (navigate → register mocks →
reload) and the native suite's app-data-wipe requirement.
Finally, the explicit **"cannot prove"** list: installer wrapper, signing,
cloud, live payments, real printers.

- [ ] **Step 3: Commit**

```
git commit -m "docs: document how to run and extend the e2e suites"
```

---

## Known gaps to assert against, not rediscover

Written into the specs as expected behaviour. If one of these changes, the tests
must be updated deliberately.

| Area | Current behaviour |
|---|---|
| `register_cloud` step | Always reports Succeeded with a "not configured yet" message; no endpoint exists |
| Staff-client `discover_server` | Always reported Skipped ("lands in Task 6"); manual URL entry is the real path |
| Terminal offline HTTP serving | Not implemented; `http_port` parsed but unused; terminal does not serve the web app |
| Runtime supervision | `restart`/`stop` exist but nothing calls them; no watchdog |
| `SetupState::reset` | Implemented and tested but **no IPC command** — hence the app-data wipe in Task 2 |
| Print bridge | Separate crate; no shell start/supervise/bundle wiring — printing assertions test the OS dialog path |
| Desktop binaries | Unsigned; SmartScreen warnings expected (owner-run) |
| Receipt printing | OS print dialog; no thermal hardware support |
| Reporting projections | Not synced; rebuilt per node — terminal reports may lag |
| Arrival cutoff | Date granularity |
| Postgres parity | 2 known failures (B10 `guest_requests.status`, B5 applier `updated_at`) |
| Auto-update | Spec approved, not implemented — nothing to test yet |

---

## Exit criteria

- [ ] Task 0 merged; every account in the login matrix authenticates and lands
      on its route; full PHP suite still green.
- [ ] `npx playwright test` is green and covers Tasks 5–10.
- [ ] `npm --prefix desktop run e2e` is green and covers Tasks 3–4, proving a
      real Postgres boot, a real FrankenPHP `/up` 200, a real terminal pull, and
      the staff client.
- [ ] `npm --prefix desktop run e2e:fast` is green in seconds and covers the
      renderer paths in Task 2b, including the `setup://step` / `setup://done`
      event handling and the IPC argument shapes.
- [ ] The release binary built **without** `--features e2e` contains no WebDriver
      server (Task 2 Step 2).
- [ ] `cargo test` still 22 passing; no new clippy warning attributable to `lib.rs`.
- [ ] Both suites are committed and documented in `docs/E2E_TESTING.md`.
- [ ] Every Known Gap is either asserted in a spec or listed as out of scope.
- [ ] The owner-run items (installer wrapper, Coolify, live payments, printers)
      are recorded as a separate, explicitly out-of-scope checklist.
