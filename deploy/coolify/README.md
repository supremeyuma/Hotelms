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

## Migration order (OWNER-RUN checklist)

Run in this order on the first deployment and on every release that adds
migrations. Steps marked **[acceptance]** state the criterion that must hold
before moving on.

1. **Provision services in Coolify** (app + postgres + scheduler + worker) and
   paste the filled environment.
   *[acceptance]* `php artisan about` inside the app container reports
   `APP_ENV=production` and the pgsql connection without errors.
2. **Baseline schema parity evidence** — run the parity test against the real
   engine (see “Schema parity” below).
   *[acceptance]* Result recorded; today the suite is a placeholder that
   passes trivially — treat a pass as non-evidence until the real gate lands.
3. **Run the migration**: `php artisan migrate --force`
   *[acceptance]* Exit code 0, no skipped migrations.
4. **Verify**: `php artisan migrate:status`
   *[acceptance]* Every row shows `Ran`; on the 2026-10-18 baseline that is
   **176/176** (176 migration files, none pending — 175 at the parity
   investigation plus the `orders.status` parity fix).
5. **Start scheduler and queue worker**; confirm with
   `php artisan schedule:list` that the sync/queue entries are scheduled.
   *[acceptance]* `schedule:list` includes `queue:work --once` (every minute)
   and `SyncWorker`; jobs pushed to the `database` queue reach a worker. On the
   cloud the `SyncWorker` entry is listed but filtered out at run time by the
   empty-peers guard (ruling R6 Option A — see “Sync topology on the cloud”).
6. **Smoke-check sync identity**: `php artisan sync:status`
   *[acceptance]* Prints the configured node id (matching the Coolify env
   value), `SYNC_NODE_TYPE=cloud`, and zero outbound peers.
7. **Attach terminals** (see “Adding a node”) and watch a sync cycle.
   *[acceptance]* `sync:status` on the terminal shows the inbound cursor
   advancing from the cloud's node id.

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
  individually). The scheduled `SyncWorker` runs a cycle every minute.

## Adding a node

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

## Schema parity

The committed gate `tests/Feature/SchemaParityTest.php` is still a placeholder
(`assertTrue(true)`) — Phase 1R (the real three-facet comparator) was deferred.
Two behaviours worth knowing before you run it:

- With the default (sqlite) connection the suite passes 1/1 and proves
  nothing.
- With `DB_CONNECTION=pgsql` it refuses to run at all:
  `tests/TestCase.php` throws *“Refusing to run tests without sqlite as the
  default database connection.”*

Until the real gate lands, evidence must be produced out-of-band (as the
2026-10-18 investigation did: information-schema comparison of MySQL vs a
fresh migrated Postgres, plus live write probes). Migrating cleanly is not
parity evidence (spec §11 rule 3). The one write-path divergence that
investigation found — `orders.status` on PostgreSQL — is now fixed by
`2026_10_18_093000_drop_orders_status_check_on_pgsql.php`; no other
engine-strictness divergences were found, but without the real gate that
remains an out-of-band claim, not an enforced one.
