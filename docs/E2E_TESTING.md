# End-to-end suites

Two suites prove the product works end to end. Both are committed, both run
against real code — no stubs of the application itself.

| Suite | Command | Drives | Speed | Authority |
|---|---|---|---|---|
| Desktop release gate | `npm --prefix desktop run e2e` | The built Tauri binary, real embedded Postgres, real FrankenPHP | ~12 min | **Signs off a release** |
| Desktop fast loop | `npm --prefix desktop run e2e:fast` | The wizard renderer in Chrome with mocked IPC | ~10 s | Development loop only |
| Web | `npx playwright test` | Laravel on SQLite, fresh seed, Chromium | ~2–4 min | **Signs off a release** |

A green fast loop never substitutes for the native run: it proves the renderer,
not the product.

## Desktop — native suite (`desktop/e2e/wdio.conf.ts`)

### Installed-artifact gate (the one that signs off a release)

The suite can drive the **installed application** instead of the debug build:

```powershell
# 1. stage the Laravel tree, runtime and Postgres, then build the installer
cd desktop
npx tauri build --features e2e --bundles nsis     # test installer (embeds WebDriver)

# 2. install it silently (per-user; NSIS supports /S)
Start-Process "$env:LOCALAPPDATA\..\desktop\src-tauri\target\release\bundle\nsis\MooreLife_0.1.0_x64-setup.exe" -ArgumentList /S -Wait

# 3. run the same walkthrough against the installed exe
$env:E2E_APP_BINARY = "$env:LOCALAPPDATA\MooreLife\desktop.exe"
npm run e2e
```

`beforeBuildCommand` runs `npm run build:app`, which builds the Vue bundle and
stages `resources/app`; `bundle.resources` maps the app tree, the PHP/FrankenPHP
runtime and the trimmed Postgres into `app/`, `runtime/` and `postgres/`. The
installer is NSIS with `installMode: currentUser` because the bundled Laravel
tree must stay writable (sessions, cache, compiled views, logs live under it).

The **release** installer is built without the feature
(`npx tauri build --bundles nsis`). It must not carry a WebDriver server: the
plugin is behind `--features e2e`, and the release binary contains no
`tauri-plugin-wdio`/`WebDriver` symbols and opens no port 4445.

### Preconditions

```powershell
# the WebDriver server only exists behind the `e2e` feature
cargo build --features e2e --manifest-path desktop/src-tauri/Cargo.toml

# a cached config would make the wizard migrate the dev MySQL database
php artisan config:clear
```

The suite refuses to start when `bootstrap/cache/config.php` exists. It also
wipes the wizard's own artifacts from `%APPDATA%\com.moorelife.desktop`
(`setup-state.json`, `.env`, `pgdata`, `postgres`, `logs`, the SQLite file …)
before a run. `EBWebView/` is never touched — that is WebView2's real profile.

**Port 8000 must be free.** The suite refuses to start when another process
holds it, because Windows lets a `127.0.0.1:8000` listener answer alongside the
server's `0.0.0.0:8000` bind — the readiness probe would then pass against an
unrelated application. The wizard itself now fails `bind_network` with the port
named instead of booting in that state.

The spec files run **in one worker, one session**, in this order: `smoke`,
`server-setup`, `terminal-and-client`. They reset the same app-data directory and
hold the same ports, so parallel workers would kill each other's servers.

### What it proves

- **server role** — unpacked Postgres cluster (`PG_VERSION`), every migration
  applied (counted against `database/migrations`), seed, admin account with
  `superuser`/`md` roles bound to a property, FrankenPHP answering `/up` with
  200, and a real HTTP login reaching `/admin/dashboard` with a session cookie.
- **terminal role** — mints a single-use pairing code, registers a node with the
  server, pulls the reference snapshot into its own SQLite file (staff accounts
  present), and the server records a credentialed `terminal` node.
- **staff client** — accepts the server's `moorelife://pair?...` link, stores
  `http://host:port`, and opens no local database.
- **wizard renderer** (smoke) — role picker renders all three roles.

The native suite drives the built binary, not the shipped installer. See
"cannot prove" below.

## Desktop — fast loop (`desktop/e2e/wdio.browser.conf.ts`)

Runs the wizard frontend against `vite dev` with `invoke()` mocked. Covers role
selection, step-list rendering, `setup://step` and `setup://done` handling
(including failure panels and the `Cause:` prefix), the pairing row, the
double-submit guard and the exact IPC payload shapes.

**Ordering gotcha:** the mock injection runs after `browser.url()` resolves, so
navigate first, register mocks, then reload — otherwise `boot()` calls
`setup_status` before any mock exists. `browser.tauri.mock(cmd)` resets the whole
mock, so set implementations in `beforeEach`, never `beforeAll`.

## Web — Playwright suite (`tests/e2e`)

Starts `php artisan serve` on port 8123 against a dedicated `database/e2e.sqlite`
that is deleted, migrated and seeded per run. `config:clear` runs first so a
cached config can never redirect the run at the dev MySQL database. The Laravel
log is truncated at start so specs never read a stale run.

### Covered today

| Spec | Proves |
|---|---|
| `smoke.spec.ts` | The public home renders (desktop and phone viewports) |
| `auth.spec.ts` | Wrong password refused; every seeded account signs in and lands on its role's dashboard; logout ends the session; a deep link survives login; password reset is accepted and logged |
| `admin-routes.spec.ts` | Every parameterless GET page under `admin/`, `reports/`, `finance/`, `hr/` renders without a 5xx or a new log exception |
| `public-site.spec.ts` | Every public page renders; gallery images actually decode; the contact form is accepted (exact flash); public menus render |
| `booking.spec.ts` | The full wizard: search, room, guest details, review, pay-at-checkout, confirmation; the reservation is asserted in the database |
| `operations.spec.ts` | Front-desk check-in, kitchen order creation through prep to ready, housekeeping finish with the staff access code, laundry board — each asserted in the database |
| `reports-finance.spec.ts` | Occupancy report data, linked summary cards and a real XLSX export; finance dashboard + revenue CSV; HR staff register; inventory list and stock locations; club POS landing |
| `guest-room.spec.ts` | A checked-in guest's QR entry reaches their room dashboard; tampered and invented tokens are refused |
| `guest-ordering.spec.ts` | A room-service order from the room page appears on the kitchen board; service requests and maintenance reports are recorded |
| `laundry.spec.ts` | Staff create a laundry service, the guest requests it, the board advances it to ready |
| `club-pos.spec.ts` | A bartender unlocks the till with a PIN, opens a docket, adds a drink, takes cash, and the docket and payment rows exist |
| `accounting-periods.spec.ts` | Tax accounts initialise; a period is opened, closed and reopened |
| `inventory.spec.ts` | Receiving stock writes one movement and updates the balance |
| `maintenance.spec.ts` | A ticket moved on the staff board is the same state the admin control room shows |
| `reports-depth.spec.ts` | Every report page renders its own heading and numbers; every export (occupancy XLSX; staff, inventory, revenue, profit & loss, balance sheet, daily revenue CSVs) returns the requested document type |
| `guest-actions.spec.ts` | Order history, cancellation, feedback, and a checkout request with an unsettled bill (refused, never 5xx) |
| `club-pos-lifecycle.spec.ts` | A docket is voided with a reason, a paid docket is reprinted, and the shift is counted and closed |
| `crud.spec.ts` | A room is created and soft-deleted, a menu item's availability toggles and restores, a staff account is created with its role |
| `authorization.spec.ts` | Five cross-role page requests are refused rather than served |
| `cross-cutting.spec.ts` | A throttled double submit sends exactly one email and shows a disabled in-flight state; bad input never answers 5xx |

### Not yet covered (the remaining walkthrough work)

Known-cell assertions inside each report (presence, headings and exports are
covered), editing/suspending/deleting staff and rooms beyond create, menu item
creation/deletion beyond the availability toggle, the POS reconcile endpoint and
receipt printing (owner-run hardware), gateway payments (deliberately never
called), guest pre-check-in, event ticketing, and public prepaid menu ordering.
`mobile` runs `smoke`, `guest-room` and the guest surface specs.

## Upgrading an existing install

`tests/Feature/Upgrade/MigrationUpgradeTest.php` is the checked-in proof that an
in-place upgrade works: it builds a database from the migrations released before
the October sync/print phase, writes settings/user/property rows, runs the full
migration set on top, and asserts the rows survived, the new schema appeared,
the new reference data exists, and a second run is a no-op.

The operator procedure is the one the installer performs on every start:

1. Back up the database (Postgres dump or copy of `hotelms.sqlite`) and the
   application data directory (`%APPDATA%\com.moorelife.desktop`).
2. Install the new build over the old one (the NSIS installer upgrades in place).
3. Start the app and let setup resume; it always runs `migrate --force`, and
   only one-shot seed/admin steps are skipped by the resume record.
4. Verify: sign in, open one report and one transactional screen.

## Cannot prove (owner-run, unavoidable)

The shipped installer wrapper (NSIS/MSI), SmartScreen and code signing, cloud
Postgres and Coolify deploys, live payment keys (the web suite never calls a real
gateway), real thermal printers, and the print bridge. A green run is not a
substitute for installing the built artifact once by hand.
