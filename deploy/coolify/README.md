# Coolify deployment — HotelMS cloud node (PostgreSQL)

This directory documents how the HotelMS cloud node is deployed on Coolify
against a managed PostgreSQL service. Nothing here is executed automatically;
every step is run by the operator (OWNER-RUN).

> ## ⚠️ Schema-parity finding on `orders.status` — FIXED (2026-10-18)
>
> A live schema-parity investigation (2026-10-18, see
> `.superpowers/sdd/2026-10-05-phase-8-cloud-deployment/task-1-report.md`)
> proved that `orders.status` is free-form `varchar(30)` on MySQL but carried
> `orders_status_check` on PostgreSQL accepting only
> `pending | preparing | ready | delivered | completed`.
>
> The application's `App\Enums\OrderStatus` also uses `processing`,
> `confirmed` and `cancelled`. Each of those three values was rejected by
> PostgreSQL with `SQLSTATE 23514` (check violation) and accepted by the
> current MySQL production database, breaking guest prepaid order creation,
> the staff order state machine, and guest order cancellation on a PG engine.
> Root cause: `2026_01_06_184410_change_orders_status_to_string.php` converts
> only MySQL to `varchar(30)`; PostgreSQL kept the constrained check created
> by `2026_01_01_120003_update_orders_table_add_service_area_and_status.php`.
>
> **Fixed** by `2026_10_18_093000_drop_orders_status_check_on_pgsql.php`,
> which drops the contradicted check on pgsql (driver-gated no-op elsewhere;
> free-form string with default `'pending'`, matching MySQL — data integrity
> unchanged). Regression coverage:
> `tests/Feature/OrderStatusPortabilityTest` persists one row per
> `App\Enums\OrderStatus` case and reads it back; against a real PostgreSQL
> 16.6 it failed with `SQLSTATE 23514` before the fix migration (4/7 values
> storable) and passed after (7/7).
>
> Remaining caveat: `tests/Feature/SchemaParityTest.php` is still a
> placeholder gate (Phase 1R deferred) — migrating cleanly is not evidence
> the schemas match (spec §11 rule 3).

## Layout

A Coolify deployment consists of:

| Service        | Image / command                                   |
| -------------- | ------------------------------------------------- |
| app            | this repo; `php artisan serve` or php-fpm + nginx |
| postgres       | official `postgres:16` image (managed volume)     |
| scheduler      | cron running `php artisan schedule:run` each minute |
| queue worker   | `php artisan queue:work --timeout=300 --sleep=3`  |

`routes/console.php` already schedules `queue:work --once` every minute, so the
scheduler alone keeps the queue moving; a dedicated worker container adds
throughput for long jobs (reporting aggregates, sync batches).

## Environment variables

Copy `postgres.env.example` into Coolify's environment editor and fill in the
placeholders. The stability rules that must never be violated:

- **`APP_KEY`** — generated once. Rotating it invalidates sessions and
  encrypted values.
- **`SYNC_NODE_ID`** — a ULID minted **once** and stored as a Coolify
  environment variable. It is read via `config/sync.php` (`env` only) and no
  PHP code generates it; the only generator lives in the desktop app's
  `envfile.rs` for terminal roles. A regenerated cloud id looks like a
  brand-new node to every terminal: they re-register and re-push their entire
  change logs, and every row the old id wrote on peers is orphaned
  (`docs/SYNC_CONVERGENCE.md`).
- **`SYNC_CREDENTIAL`** — shared secret, minted once, identical on every
  node. Peers store only its SHA-256.

### Sync topology on the cloud

`SYNC_ENABLED=true`, `SYNC_NODE_TYPE=cloud`, and **`SYNC_PEERS` empty**: the
cloud is a node others connect to; it never dials out. `SyncWorker` iterates
`sync.peers`, so an empty list means no outbound cycles (Phase 8 decision,
ruling R6 Option A — no cloud push worker; the worker's empty-peer path is
guarded).

**Do not remove the guard.** The guard is a `->when(fn () => ! empty(config('sync.peers')))`
filter on the `SyncWorker` entry in `routes/console.php`; registration stays
unconditional so `schedule:list` still shows the worker on every node, and the
filter decides at run time whether there is anywhere to sync to. An
unconditional loop restored on the cloud would iterate an empty peer list
every minute — a wasted schedule slot and a misleading green dashboard, which
is exactly what Option A rejected. Covered by
`tests/Feature/Sync/SyncScheduleGuardTest`.

Peering is bidirectional and pairwise: a star does **not** converge,
and there is no relay — every pair of nodes that must exchange changes has to
be peered directly (triangle for three nodes). See `docs/SYNC_CONVERGENCE.md`.

## Deployment checklist (OWNER-RUN)

Run in this order on the first deployment and on every release that adds
behaviour. Steps marked **[acceptance]** state the criterion that must hold
before moving on.

1. **Provision services in Coolify** (app + postgres + scheduler + worker) and
   paste the filled environment.
   *[acceptance]* `php artisan about` inside the app container reports
   `APP_ENV=production` and the pgsql connection without errors.
2. **Baseline schema parity evidence** — run the parity test against the real
   engine (see “Schema parity” below).
   *[acceptance]* Result recorded; today the gate is a placeholder that
   passes trivially — treat a pass as non-evidence until the real gate lands.
3. **Run the migration**: `php artisan migrate --force`
   *[acceptance]* Exit code 0, no skipped migrations.
4. **Verify**: `php artisan migrate:status`
   *[acceptance]* Every row shows `Ran`. The count tracks the migration files,
   so check it rather than trusting a literal: at HEAD it is **177/177**
   (`(Get-ChildItem database/migrations -Filter *.php).Count` = 177).
5. **Start scheduler and queue worker**; confirm with
   `php artisan schedule:list` that the sync/queue entries are scheduled.
   *[acceptance]* `schedule:list` includes `queue:work --once` (every minute)
   and `SyncWorker`; jobs pushed to the `database` queue reach a worker. On the
   cloud the `SyncWorker` entry is listed but filtered out at run time by the
   empty-peers guard (ruling R6 Option A — see “Sync topology on the cloud”).
6. **Smoke-check sync identity**: `php artisan sync:status`
   *[acceptance]* Prints the configured node id (matching the Coolify env
   value), `SYNC_NODE_TYPE=cloud`, and zero outbound peers.
7. **Register one node** by pairing code (see “Adding a node” → “By pairing
   code”), then watch a sync cycle.
   *[acceptance]* `sync_nodes` contains the new row (`status = active`) and a
   non-null `last_seen_at` set by the first authenticated request; `sync:status`
   on the terminal shows the inbound cursor advancing from the cloud's node id.
8. **Run the Task 4 integration round trip against staging** (not production —
   the test writes). Stage a scratch/staging Postgres, migrate it, then run the
   cloud round trip with the Postgres opt-in:

   ```powershell
   $env:HOTELMS_TEST_ALLOW_PGSQL = '1'
   $env:DB_CONNECTION = 'pgsql'
   $env:DB_HOST = '<staging-host>';  $env:DB_PORT = '5432'
   $env:DB_DATABASE = '<staging-db>'; $env:DB_USERNAME = '<user>'; $env:DB_PASSWORD = '<secret>'

   php artisan test --filter=CloudSyncIntegrationTest   # or the full suite
   ```

   *[acceptance]* `CloudSyncIntegrationTest` is green (7 tests — a terminal sale
   reaches the cloud exactly once, and a cloud booking reaches the terminal).
   The `HOTELMS_TEST_ALLOW_PGSQL=1` opt-in is required: without it the suite
   refuses any non-sqlite default, and with it **only** pgsql is permitted
   (`tests/TestCase.php:28-44`). Running the full suite on Postgres still shows
   the two known failures below — see “Schema parity”.
9. **Promote**: point the production host at the verified image and repeat
   steps 3–6. The round trip in step 8 is staging evidence, not a reason to skip
   the production migration check.

Backup: take a Postgres logical backup (`pg_dump`) before step 3 on any
environment that holds real data.

## Reading sync state on a production machine

- `php artisan sync:status` — node id, enabled state, peers, and per-peer
  cursors: `direction = 'in'` (how far this node has applied changes from that
  peer) and `direction = 'out'` (how far it has pushed its own log to that
  peer). Cursors are keyed by peer node id, not address, so repointing a peer
  at a new base_url loses no progress.
- `php artisan sync:health` — one table of active/inactive nodes, recorded
  changes, changes older than 24h, unresolved conflicts and cursors, plus a
  warning line when conflicts are unresolved or nodes are not active.
  Output-only (there is deliberately no public health endpoint) and **not
  scheduled**: this deployment has no monitoring to alert on it, so run it by
  hand.
- `php artisan sync:run` — one full cycle by hand (also push/pull
  individually). On a node with peers the scheduled `SyncWorker` runs a
  cycle every minute; on the cloud the empty-peers guard filters it out
  (see "Sync topology on the cloud").

**Unresolved conflicts.** A Class 4 change (bookings, `pos_sessions`) that
cannot merge is written to `sync_conflicts` and left for a human; `sync:health`
counts them under `unresolved conflicts` and warns. Inspect the rows
(`php artisan tinker`), decide the winning value, apply it on the authoritative
node, and mark the conflict resolved; the next cycle re-exchanges the row. A
conflict never blocks the rest of the batch.

## Adding a node

### By pairing code (preferred — no shared secret to distribute)

The cloud mints a single-use, time-limited code; the node exchanges it for its
own credential. Only the SHA-256 hash of that credential is stored.

1. **On the cloud, mint a pairing code** bound to one property:

   ```
   php artisan sync:pairing-code --property=<property-id> --ttl=15
   ```

   Options: `--property` (required, a property id) and `--ttl` (minutes, at
   least 1, default **15**). The code is 12 characters of `[A-Z0-9]`, meant to
   be read aloud and typed; the plaintext is printed **once**.
   (`app/Console/Commands/SyncPairingCode.php`.)

2. **On the terminal, redeem it** against the cloud's registration endpoint,
   `POST https://<public-host>/api/sync/register` (JSON):

   ```powershell
   Invoke-RestMethod -Method Post -Uri 'https://<public-host>/api/sync/register' `
     -ContentType 'application/json' `
     -Body (@{
       property_code = '<property-id>'
       pairing_code  = '<CODE>'
       node_name     = 'Bar Terminal 1'
       node_type     = 'terminal'      # or 'lan_server'
     } | ConvertTo-Json)
   ```

   A `201` response is `{ "node_id": "...", "credential": "...", "cloud_url": "..." }`.
   The plaintext `credential` is returned **exactly once** and never again —
   store it on the terminal as `SYNC_NODE_ID` (`node_id`) and `SYNC_CREDENTIAL`
   (`credential`).

   The endpoint is **throttled to 10 requests/minute** and sits *outside* the
   `sync.node` middleware group (the caller holds no credential yet).
   (`routes/api.php:14-15`, `app/Http/Controllers/Sync/NodeRegistrationController.php`.)

3. **On the terminal, set `SYNC_PEERS`** to include the cloud (see the shared
   credential path below), then restart/redeploy and run `sync:run` once.

**Pairing-code rules (verified at HEAD).** A code is **single-use** — redemption
flips `used_at` atomically inside the transaction, so a concurrent or repeated
redeem gets `422` and mints no node — and **time-limited** (`expires_at > now()`,
default 15 minutes). It is **bound to one property**: a code minted for property
A is rejected with the same generic `422` if the request names property B. The
credential is stored as `hash('sha256', $secret)` and the `sync.node` middleware
compares with `hash_equals` (`app/Http/Middleware/AuthenticateSyncNode.php:30`),
so a database leak yields no usable credential. A rejected attempt spends
nothing.

`php artisan sync:register --name=... --type=terminal --property=<id>` is the
**local** alternative: it creates the node row on this machine and prints its
node id and credential once (SHA-256 stored), for when the cloud endpoint is not
reachable or the node is already trusted.

### By shared credential

1. Mint on the new node: its own stable `SYNC_NODE_ID` (ULID, stored once)
   and reuse the cluster-wide `SYNC_CREDENTIAL`.
2. On the new node set `SYNC_PEERS` to every existing node it must exchange
   changes with, e.g.
   `SYNC_PEERS={"cloud":{"base_url":"https://<public-host>"},"node-a":{"base_url":"http://10.0.0.2:8000"}}`.
3. On every existing node add the new node to `SYNC_PEERS` the same way —
   peering is pairwise; a terminal that only peers with the cloud converges
   with the cloud and nobody else.
4. Restart/redeploy the affected services and run `sync:run` once by hand.
   *[acceptance]* `sync:status` on both sides shows both cursors advancing;
   a second run reports an empty pending count.

Terminals behind NAT that cannot be dialled must push directly to every node
they need to reach (known limit, no relay).

## Recovery: “a terminal is not appearing on the cloud”

Work down this list; each check is cheap and rules out one class of cause.

1. **Pairing code** — was it minted, is it unexpired, is it unconsumed? Codes
   default to 15 minutes (`sync:pairing-code --ttl=<minutes>`) and are
   single-use. Inspect `sync_pairing_codes` (`expires_at`, `used_at`). A spent
   or expired code is a `422` at registration, not a failed sync.
2. **Credential stored on the terminal** — `SYNC_NODE_ID` and
   `SYNC_CREDENTIAL` must both be present in the terminal's environment. The
   credential is delivered once at registration and is unrecoverable; if it was
   lost, re-register the node (mint a fresh code) rather than guessing.
3. **Node `status`** — the `sync_nodes` row must be `active`. A non-active node
   is refused `403` by the middleware (`AuthenticateSyncNode.php:34-36`) no
   matter how healthy its network is.
4. **`last_seen_at`** — stamped on every authenticated request from that node
   (`AuthenticateSyncNode.php:38`). Null means the node has **never**
   authenticated: the problem is reachability or credentials, not a stuck
   cursor. Fresh-but-static means the node reached the cloud once and then
   stopped.
5. **`SYNC_PEERS` on the terminal** — the terminal must list the cloud
   (`base_url` = the cloud's public URL). Peering is pairwise: a terminal with
   no cloud peer never dials out. Beware a base_url typo or a trailing slash.
6. **Firewall / network** — the terminal must be able to reach
   `https://<public-host>/api/sync/*` outbound, and the Coolify reverse proxy
   must route those paths to the app. Confirm from the terminal host itself
   (`Invoke-WebRequest https://<public-host>/up`).
7. **Clock skew (NTP)** — give this its own look. Change ordering is
   Hybrid-Logical-Clock based: `App\Sync\Hlc` stamps wall-clock milliseconds
   (`Hlc::now`, `microtime(true)`) plus a logical counter, and merges by
   comparing timestamps. The logical counter keeps causality intact for changes
   that have actually been exchanged, but it cannot repair **gross wall-clock
   skew** between nodes that have not yet communicated, and skew degrades merge
   ordering. An operator seeing merge anomalies or out-of-order changes should
   check NTP on both the terminal and the cloud **first**, before suspecting the
   engine.

## Known limitations (verified at HEAD, 2026-10-19)

Carried forward honestly rather than discovered in production.

- **Reporting projections are not synchronized.** Every `reporting_*` table is
  listed under `excluded` in `config/sync_scope.php:190-230`, and none appears
  in `app/Sync/SyncEntityRegistry.php` (15 registered entities). Each node
  rebuilds projections locally from Class 1 source rows (spec §6.1).
- **The arrival cutoff is evaluated at date granularity.** `bookings.check_in`
  is a date; `BookingAuthority::isLocalWindow()` compares the arrival's
  `endOfDay()` against `now()->addHours(cutoff)`
  (`app/Services/Booking/BookingAuthority.php:58-66`). At the default cutoff of
  48h the effective local window is **48–72 hours**, not 48h exactly.
- **Receipt printing is generic ESC/POS via a loopback daemon** — the “OS print
  dialog” wording is stale. Phase 7 shipped `desktop/print-bridge/src/{main,server,sink}.rs`,
  which listens on **loopback only** (`127.0.0.1:9100`, `config/pos.php`) and
  owns the printer; the app queues `print_jobs` and renders bytes with
  `App\Print\EscPosReceiptRenderer`, drained by `php artisan print:dispatch`
  (invoked from the POS receipt path's terminating callback,
  `app/Http/Controllers/ClubPos/DocketController.php:171` — it is **not** a
  scheduled command). The default sink is `stdout`; a real device is selected
  with `PRINT_BRIDGE_TARGET` (`serial:`, `tcp:host:port`, `file:`). **Physical
  hardware and the paper-out/failure paths are owner-run and were never
  exercised on this machine.** The daemon is **not yet bundled into the desktop
  installer** (`desktop/src-tauri/tauri.conf.json` declares no bundled binary or
  resource for it).
- **Desktop binaries are unsigned.** `desktop/src-tauri/tauri.conf.json` has a
  `bundle` block but **no** `signingIdentity` / certificate configuration, so a
  `tauri build` output is unsigned; `.github/workflows/desktop-build.yml` runs
  `cargo build --release` only (no installer, no signing step). Expect Windows
  SmartScreen / “unknown publisher” warnings on install.
- **Sync is off by default.** `config/sync.php:66` is
  `'enabled' => (bool) env('SYNC_ENABLED', false)`. An unconfigured deployment
  behaves exactly as it did before sync existed (spec §10).
- **Option A — no cloud push worker, by design.** The cloud never dials out;
  the empty-peer `SyncWorker` guard is deliberate and must not be removed (see
  “Sync topology on the cloud”).
- **Credentials are shown once.** `sync:register` and the pairing exchange
  return the plaintext credential a single time; only its SHA-256 hash is
  stored, so it cannot be recovered — re-register instead.
- **Trusted proxies are not configured (backlog B7).** `bootstrap/app.php` does
  not call `trustProxies()`, so rate limiters — including the
  `throttle:10,1` on `/api/sync/register` and the login limiter — key on
  `REMOTE_ADDR`. Behind Coolify's/reverse-proxy egress, many clients may share
  the proxy IP: the pairing throttle can over-block all redeemers at once, or
  per-client limits become meaningless. Decide the proxy topology and configure
  trusted proxies before relying on per-client throttling.
- **`guest_requests.status` omits `requested` (backlog B10).** The column’s
  original enum is `pending | acknowledged | completed`
  (`database/migrations/2025_12_23_232705_create_guest_requests_table.php:15`),
  but `LaundryOrderService` writes `requested`
  (`app/Services/LaundryOrderService.php:118`, `LaundryStatus::REQUESTED`).
  **On PostgreSQL, laundry order creation fails** (`SQLSTATE 23514` check
  violation); MySQL in strict mode errors, and in non-strict mode stores an
  empty string silently. Masked because the suite runs on SQLite. Fix = add
  `requested` to the allowed set on both engines (a business-rule decision).
- **The schema-parity gate is still a placeholder (backlog B1).**
  `tests/Feature/SchemaParityTest.php` is `assertTrue(true)`; a pass proves
  nothing. See “Schema parity”.

## Schema parity

The committed gate `tests/Feature/SchemaParityTest.php` is still a placeholder
(`assertTrue(true)`) — Phase 1R (the real three-facet comparator) was deferred.
A pass is non-evidence until the real gate lands. Two behaviours worth knowing
before you run it:

- With the default (sqlite) connection the suite passes 1/1 and proves nothing.
- With `DB_CONNECTION=pgsql` the suite **can** now be run: setting
  `HOTELMS_TEST_ALLOW_PGSQL=1` opts in and permits **exactly** pgsql
  (`tests/TestCase.php:28-44`). Without the flag it still refuses any
  non-sqlite default, so a stray MySQL connection is never reached.

Honest current result of the whole suite against PostgreSQL: **2 known
failures** (backlog **B10** `guest_requests.status` and **B5/F8** the applier
stamping a local `updated_at`) out of 415 tests at the last real-engine run.
Both are genuine and recorded; the cloud sync round trip itself is green on PG.

Until the real gate lands, evidence must be produced out-of-band (as the
2026-10-18 investigation did: information-schema comparison of MySQL vs a
fresh migrated Postgres, plus live write probes). Migrating cleanly is not
parity evidence (spec §11 rule 3). The one write-path divergence that
investigation found — `orders.status` on PostgreSQL — is now fixed by
`2026_10_18_093000_drop_orders_status_check_on_pgsql.php`; no other
engine-strictness divergences were found, but without the real gate that
remains an out-of-band claim, not an enforced one.
