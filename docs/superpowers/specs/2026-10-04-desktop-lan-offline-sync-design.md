# Desktop, LAN Server, Offline Operation and Cloud Sync — Design

**Date:** 2026-10-04
**Status:** Approved
**Scope:** HotelMS desktop application, on-premise LAN server, offline-capable Club POS + Inventory, and cloud synchronization.

---

## 1. Problem

Six requirements drive this work:

1. One PC or Mac acts as the server. Other machines on the same network, with the desktop app installed, read from and write to it.
2. The server PC is itself usable while serving.
3. Server and client configuration is done entirely inside the desktop app, without outside help.
4. The server PC syncs up to the cloud whenever internet is available.
5. The web version continues to function.
6. Club POS and Inventory run as their own desktop application that operates offline for long periods and syncs to the cloud as soon as internet is available. Managers, administrators and owners can see that data.

Requirement 6 was confirmed to mean **full multi-master**: a POS terminal must continue selling and consuming stock with the LAN server powered off, and reconcile later.

## 2. Current State

No plan, design document, ADR or TODO anywhere in the repository addresses desktop applications, LAN servers, offline operation, synchronization, or cloud federation. The only roadmap present is reporting-only (`REPORTING_ARCHITECTURE_BLUEPRINT.md`).

Verified position as of this document:

| Area | Status |
|---|---|
| Desktop application | Absent. No Electron, Tauri, NW.js or PWA scaffolding; no such dependency in `package.json`. |
| LAN / on-premise server | Absent. No LAN, on-site or intranet references in the repository. |
| Offline operation | Absent. No service worker, no IndexedDB, no offline queue. Note: all ~40 existing occurrences of "offline" mean `payment_method = 'offline'` (pay at counter) and are unrelated. |
| Synchronization | Absent. No change log, no outbox, no version column, no cursor. |
| Cloud synchronization | Absent. Only empty AWS placeholders in `.env.example:59-63`. |
| API surface for clients | `routes/api.php` contains two payment webhook endpoints only. |
| Test suite | Red at baseline: 52 failed, 1 passed, 78 pending. |
| SQLite schema build | **Succeeds.** All 165 migrations apply on SQLite as of Phase 0R. |
| Club POS | Backend substantially real — schema, `ClubPosService`, `DrinkStockService`, five controllers, 16 routes. Frontend is a non-functional scaffold. Zero tests. |
| Inventory | Mature and ledger-based — `InventoryService`, immutable `InventoryMovement`, unique `(item, location)`, feature tests present. |

### 2.1 Blocking constraint

`technical layout.txt.txt` mandates Hostinger shared hosting and explicitly forbids what this design requires:

- `:13-15` — must run "without long-running processes or self-hosted websocket servers"
- `:57` — "No Docker on production"
- `:58` — "No Redis"
- `:40-47` — database queue driver plus minutely cron only

`deploy.sh:5` hard-codes `/home/u776774917/domains/mooreliferesort.com/Hotelms`. `app/Console/Kernel.php:22-54` implements the cron model that constraint requires, and is itself dead code under Laravel 12 since `bootstrap/app.php:9-13` wires only `web:`, `api:`, `commands:` and `health:`.

An always-on LAN server holding authoritative data is incompatible with that specification. Superseding it is an explicit decision recorded in this document, not an implementation detail.

### 2.2 SQLite cannot build the schema — and terminals depend on it

`migrate:fresh` on SQLite previously aborted at `2026_04_12_000001_create_reporting_events_table.php`, because Laravel's schema builder throws `This database driver does not support fulltext index creation` for the `fullText` call at line 34. That call is now guarded by `if ($driver !== 'sqlite')`, and `migrate:fresh` completes all 165 migrations on SQLite as of Phase 0R. A real SQLite fulltext index would need an `fts5` virtual table plus matching sync triggers; no application query currently uses `MATCH`, so that remains a separate decision.

The 24 migrations that consequently never run on SQLite are all 12 reporting tables (`reporting_events` through `reporting_metric_definitions`), the `jobs` and `failed_jobs` tables, the kitchen menu items table, **and all 9 Club POS tables** (`pos_devices` through `reporting_pos_facts`).

Consequences:

- The test suite is red at baseline — 52 failed, 1 passed, 78 pending — because tests run against in-memory SQLite (`phpunit.xml:26-27`, enforced at `tests/TestCase.php:26-28`) and tables such as `events` do not exist.
- Club POS has no tables and therefore no tests. Its zero test coverage is a consequence, not an oversight.
- **This directly blocks the design.** Decision 4 puts SQLite on every POS terminal and decision 5 makes terminals run migrations against their own SQLite file. A terminal cannot be built until this is fixed.

`fullText` appears in exactly one migration. This is a bounded fix, though more failures may surface among the 24 migrations that have never been exercised on SQLite.

### 2.3 Defects that block POS

| Location | Defect |
|---|---|
| `app/Http/Controllers/ClubPos/DashboardController.php:28-29` | Hard-codes `'openDockets' => []` and `'currentSession' => null`, so dockets can never populate |
| `resources/js/Pages/ClubPos/Dashboard.vue:81` | Calls `route('club.pos.dockets.items.add')`; `routes/club.php:43` names it `pos.dockets.items.store` |
| `resources/js/Pages/ClubPos/Dashboard.vue:90-94` | `createDocket()` requires a session that can never exist |
| `app/Models/PosDevice.php` | Exists but is never instantiated; no seeder, no registration endpoint |
| `app/Services/ClubPosService.php:26` | `getOrCreateSession()` is never called by any controller, so no shift can open |
| `app/Http/Controllers/ClubPos/DashboardController.php:24-25` | Passes `$device = null` straight into Inertia |
| `app/Http/Controllers/ClubPos/StockController.php`, `ReportController.php` | Return raw JSON with no Vue consumer |
| — | No receipt or print implementation, despite `PosPrintLog` existing |

Also logged at `UI_AUDIT.md:66,70,78,80`.

## 3. Decisions

| # | Decision |
|---|---|
| 1 | Offline means full multi-master. Terminals survive LAN server failure. |
| 2 | The cloud is a VPS running Coolify with Docker. |
| 3 | Conflict policy is domain-scoped, assigned per record type. |
| 4 | Postgres on server-class nodes; SQLite on terminals. |
| 5 | Terminals bundle a local PHP runtime rather than reimplementing business rules. |
| 6 | Designed for possible multi-property: protocol and node identity ready, data partitioning deferred. |
| 7 | Desktop shell is Tauri v2. |
| 8 | Cloud is the sync hub; the LAN server is a low-latency participant. |
| 9 | Booking authority is partitioned by arrival date, with a 36-hour cutoff. |

### 3.1 Superseded constraints

The following in `technical layout.txt.txt` are superseded: `:15` (no long-running processes), `:40-47` (database queue and cron-only), `:54-59` (no Docker, no Redis, no socket broadcasting on production).

The cloud runs Docker on a VPS via Coolify, matching the path already prepared in `Dockerfile` (108 lines), `docker/entrypoint.sh:20-37` (web, worker and scheduler roles) and `docker/nginx/default.conf:2`. Shared-hosting deployment remains available as a fallback and is not removed.

## 4. Node Topology

Four node kinds run the identical Laravel application. Only `.env` differs — driver, role, property binding and sync peer configuration.

| Node | Runs | Local store | Role |
|---|---|---|---|
| **Cloud** | Coolify VPS, Docker | Postgres | Web version and sync hub. Holds all properties. |
| **LAN server** | One PC or Mac | Postgres | Full application, serves staff over LAN. Bound to one property. |
| **POS terminal** | Desktop, Tauri v2 | SQLite + bundled PHP | Sells and consumes stock with no network at all. |
| **Staff client** | Desktop, Tauri v2 | None | Loads the application from the LAN server over HTTP. |

### 4.1 The staff client is thin

It holds no database and runs no business logic. It is a Tauri window pointed at `http://<lan-server-ip>`. This keeps roughly thirty staff deployments cheap to install and free to update, and means a staff client failure cannot corrupt data. A local read cache to survive a LAN server restart mid-shift is deliberately out of scope.

### 4.2 One codebase

Every node runs the same application. `ClubPosService`, `PricingService`, `DrinkStockService` and `InventoryService` are the same files on a terminal as on the cloud. This is the reason for bundling PHP (decision 5) rather than reimplementing rules in TypeScript: pricing, VAT, service charge and stock rules have exactly one implementation.

### 4.3 Peer lists

Following decision 8:

- **Terminal** → LAN server (preferred, LAN, fast) → cloud (fallback, internet)
- **LAN server** → cloud, plus each terminal
- **Cloud** → every node

A terminal always has somewhere to sync to. A dead LAN server degrades throughput; it does not stop reconciliation.

### 4.4 Property identity

Every syncable row carries `property_id`. The node registry is keyed by `(cloud, property)`. Terminals and LAN servers bind to one property during setup. Staff accounts remain shared across properties, matching current behaviour.

Deliberately **not** included: per-property users, property-scoped Spatie roles, tenant global scopes, property-scoped cache keys or sessions. Onboarding a second property requires new rows, a new LAN server and new terminals — not a code change.

The current codebase is single-property with a vestigial `properties` table: `app/Services/BookingService.php:102` hard-codes `property_id => 1`, and `database/seeders/PropertiesSeeder.php:14-20` inserts one row.

## 5. Sync Engine

### 5.1 Hybrid Logical Clock

Every syncable row gains three columns:

- `sync_hlc` — hybrid logical clock value
- `sync_origin_node_id` — node that last wrote the row
- `sync_deleted_at` — tombstone timestamp

The HLC is roughly wall-clock but respects causality, so it is comparable across nodes and immune to the clock skew that would otherwise cause silent data loss. Cross-node recency comparison uses `sync_hlc` with `sync_origin_node_id` as a deterministic tie-break.

### 5.2 Append-only change log

Every write to a syncable row appends one `sync_changes` row containing the full row snapshot, entity type, entity id, operation, `sync_hlc` and origin node. Nodes push changes newer than the cursor they hold for a peer and pull changes past their own cursor.

Full snapshots rather than diffs. At ten nodes and this data volume the bandwidth is irrelevant, and snapshots eliminate an entire class of bug.

### 5.3 Merge is a pure function

```
merge(entityType, localRow, remoteRow, policy) -> row | conflict
```

Given identical inputs it returns an identical output on any node. This is what makes it safe for a terminal and the cloud to merge the same two changes independently and still converge. The design rests on this property.

### 5.4 Schema additions

New tables, created by new migrations:

- `sync_nodes` — registry: node id (ULID), `property_id`, role, credential, `last_seen_at`
- `sync_changes` — append-only change log with per-node monotonic sequence
- `sync_cursors` — `(peer_node_id, last_applied_change_id)` pairs
- `sync_conflicts` — unresolved clashes awaiting a decision, with local payload, remote payload, policy and resolution

Every row retains the ULID minted at its origin node, so replaying a change is a primary-key no-op and application is idempotent.

## 6. Conflict Policy Catalogue

Classification principle: **does the row represent something that happened, or something that is currently true?**

### Class 1 — Immutable facts. Append-only, never merged.

`pos_dockets`, `pos_docket_items`, `pos_docket_payments`, `pos_print_logs`, `inventory_movements`, `drink_stock_movements`, `audit_logs`.

Once written, never edited. Two nodes creating rows of the same class both survive. No conflict is possible.

`payments` was listed here in the original draft. It does not belong: the
application mutates `payments.status` and `payments.refunded_at` in place when a
payment is refunded (`app/Services/FlutterwaveService.php`), so "once written,
never edited" was already false. It is Class 4, below.

### Class 2 — Mutable reference data. Row-level last-write-wins.

`inventory_items`, `inventory_locations`, `room_types`, `rooms`, `staff_profiles`.

**Row-level** last-write-wins: the row with the higher `sync_hlc` replaces the
other, with `sync_origin_node_id` breaking an exact tie. Concurrent edits to
different fields of the same row resolve in favour of the later write; this is a
known and accepted limitation. Never surfaced to a human.

Not implemented: field-level last-write-wins. It would require a per-field HLC
map on every Class 2 row, maintained on every write. The schema carries one
`sync_hlc` per **row**, which cannot express per-field write times. If a lost
edit is ever observed in practice, add `sync_field_hlc JSON` and change the
policy. The accepted risk, stated plainly: one terminal changing a room's rate
discards another terminal's simultaneous change to its description.

### Class 3 — Derived balances. Never synced as authoritative; recomputed.

`inventory_stock.quantity`, `drink_stock` bottle counts.

These are caches of a sum over Class 1 rows. Each node computes the sum in a deterministic total order — `(occurred_at, origin_node_id, movement_id)` — so every node reaches an identical balance without exchanging balances. There is nothing to reconcile, so no conflict is possible.

### Class 4 — Hard conflicts. Blocked, surfaced to a human.

- Two bookings on the same room for overlapping dates. Overlap detection already exists at `app/Services/RoomAvailabilityService.php:229-233` and is reused.
- Two `pos_sessions` claiming one `pos_devices` row. The link column is `sessions.device_id`, a foreign Ulid to `pos_devices`, and the two sessions claim it when their `[opened_at, closed_at)` intervals intersect. A null `closed_at` means still open, so it has no upper bound.
- A payment captured on one node and refunded on another. The two rows are the same payment seen on both nodes: they conflict only when both sides have reached a terminal `status` and those statuses point opposite ways. `pending` against `paid` is a normal progression and never blocks.

Never auto-resolved. Per decision 9, the booking case is architecturally unreachable — see §8.

Resolved: `payments` is Class 4, not Class 1. This section and §6 Class 1
disagreed about it, and Class 4 wins because the application mutates
`payments.status` in place on refund, so the Class 1 premise was already false.
Had Class 1 been chosen instead, a capture-against-refund split would have
resolved silently by newest timestamp with no human ever seeing it.

### Class 5 — Cloud-authoritative configuration. Cloud wins, nodes do not write.

`settings`, `roles`, `permissions`, POS menu and pricing configuration. Pushed down, never contested. The 36-hour booking cutoff (§8) lives here.

### 6.1 Reporting projections are not synchronized

`reporting_pos_facts` and the rest of `app/Reporting/Projectors/` are derived from Class 1 rows. Each node rebuilds its own projections from synchronized source rows. Synchronizing derived data would import projection drift — two nodes disagreeing about a total because one rebuilt at a different moment.

### 6.2 InventoryService must change shape

`app/Services/InventoryService.php` currently writes a balance and a movement in one transaction. On a synchronizable node the movement is the truth and the balance is a rebuildable cache. Both are still written, but the balance must be reconstructible at any moment. This requires a rebuild command and a test asserting `rebuild == incremental`. Without that test the ledger quietly diverges and the failure surfaces only at stocktake.

## 7. Terminal Synchronization Scope

A terminal does not synchronize the whole hotel database. It synchronizes the transitive closure of what POS and inventory actually read and write:

- POS tables (Class 1 and Class 3 above)
- Inventory tables
- `bookings`, `charges`, `journal_entries` and `journal_lines` — required because `app/Services/ClubPosService.php:140-152` posts `room_charge` sales to a guest folio, which cannot be evaluated offline without booking data. There is no `folio` or `ledger` table; the ledger is `charges` + `journal_entries` + `journal_lines`.
- `users` and `roles` — authentication and authorization. There is no `permissions` table in active use; authorization is role-based via Spatie's `hasRole`/`hasAnyRole`.

A test fails if a new dependency is added that the manifest does not cover.

The verified manifest, the per-table policy class, the tables deliberately left
out and the reasons for each live in [`docs/SYNC_SCOPE.md`](../../SYNC_SCOPE.md)
and [`config/sync_scope.php`](../../../config/sync_scope.php). The guard is
`tests/Feature/Sync/SyncScopeTripwireTest.php`.

## 8. Booking Authority

Authority is partitioned by **arrival date**, not booking date.

| Guest wants a room for | Front desk may book on LAN? | Online may book? |
|---|---|---|
| Now (walk-in) | Yes | Closed |
| Tomorrow | Yes | Closed |
| Three weeks out | No | Yes |

- **Arrival beyond 36 hours** — cloud is authoritative. All such bookings are made on the cloud and pushed down to the LAN server. The 36-hour margin guarantees the LAN server holds the reservation long before arrival, and provides 36 hours of slack to notice a failed sync.
- **Arrival within 36 hours** — online booking is closed and the front desk is authoritative. Walk-ins and short-notice arrivals are booked on the LAN server and pushed up.

Applying the cutoff to both channels, rather than online only, is what removes the split-authority race. The cutoff alone does not: without it, a front-desk booking made for a guest arriving in three weeks sits in the cloud-authoritative window, and a subsequent online booking for the same room would produce exactly the Class 4 conflict the rule was meant to make rare — with the guest already holding a confirmation.

**Walk-ins are unaffected.** A walk-in's arrival date is today, so it falls inside the front-desk window. The front desk books it on the LAN server exactly as it does today, and it reaches manager reporting within synchronization latency. Walk-ins get the better path, because the LAN server holds the freshest room state.

The only workflow the cutoff changes is a phone or walk-up reservation for a future date beyond 36 hours. Realistic handling is that the desk takes the guest's details and either walks them through online booking or takes a deposit and finalizes on the cloud. If this proves painful in practice, a provisional-hold flow is a clean later addition and does not disturb the partition.

This is a business change as well as a code change and must be communicated to front-desk staff before it ships.

## 9. Desktop Application

### 9.1 Bundled runtime

**FrankenPHP** — a single static binary embedding Caddy and PHP. Cross-platform, runs Laravel in worker mode for genuine concurrency. One binary ships per platform, supervised as a child process by the Tauri application. The same runtime serves terminals, staff-facing local serving and the LAN server; only `.env` differs.

Required extensions: `pdo_sqlite`, `pdo_pgsql`, `bcmath`, `intl`, `zip`, and **GD** for image processing. GD was chosen over Imagick on 2026-10-05 because it ships inside the PHP runtime itself, which keeps the Windows packaging surface smaller; Imagick would have introduced a separate C library with its own licence terms. This is still real packaging work per platform and remains the largest technical risk in this design (§14).

**Portable Postgres for the server role.** Windows offers no clean way to install Postgres without administrator rights, and Docker Desktop would violate both `technical layout.txt.txt:57` and requirement 3. An embedded Postgres build unpacked into the application's data directory and supervised as a child process avoids an installer entirely. If this proves fragile on a target OS, the fallback is a system Postgres, which reintroduces the installer requirement 3 exists to avoid.

### 9.2 Setup paths

One installer. Each role is chosen by the first question asked.

**Server.** "Set up this PC as server" unpacks Postgres into the application data directory, generates `.env`, runs migrations, seeds, creates the administrator account and property binding, binds to `0.0.0.0`, registers a firewall rule, registers with the cloud using a pairing code, and installs itself as a tray application that starts on login. The setup screen displays the LAN address and a pairing QR code.

**POS terminal.** "Set up as POS terminal" requests a property code and pairing code, creates the local SQLite file, runs migrations, pulls the initial snapshot, and registers as a node. No server URL is required.

**Staff client.** "Connect to a server" discovers the server on the LAN, opens a window pointed at it, and staff log in normally. No database, no migrations, nothing to break.

### 9.3 Discovery

A UDP broadcast probe on a fixed port, with the pairing QR code and manual IP entry as fallbacks. Preferred over mDNS, which is unreliable on Windows networks and often blocked by group policy.

### 9.4 Runtime route manifest blocker

`resources/js/ziggy.js` is a 35.5 KB route manifest baked at build time against a fixed origin. A staff client pointed at a LAN server would resolve routes against whatever host built the assets. Runtime route fetching must be fixed before any node other than the cloud can serve the application. This is a prerequisite, not a phase item.

> **Correction (2026-10-08).** The blocker was investigated before acting and
> largely **does not hold** for the installed Ziggy **v2.6.0**: `@routes`
> constructs the manifest per requesting host (`new Ziggy` with no URL falls
> back to `rtrim(url('/'), '/')`, i.e. the request root, and `app/` sets no
> `forceRootUrl`). A live probe with a non-local `Host` header rendered
> `"url"` matching the requesting host, so a staff client already resolves
> every `route()` against the LAN server that served the page. The referenced
> `ziggy.js` was dead — nothing imported it — and it has been **deleted and
> gitignored** (gap G52). The remaining real defects were the empty-leaf route
> names (gap G51, 21 routes, all named) and a missing guarantee that no built
> asset can bake an origin again (`ZiggyRouteManifestTest`, four guards). Full
> detail: `docs/superpowers/plans/2026-10-05-ziggy-runtime-route-manifest.md`.
> Requirement 2's "no hardcoded LAN/server URL in client code" is satisfied by
> the request-root behaviour; no runtime fetch bootstrap is required.

## 10. Web Version

Untouched in behaviour. Same Coolify deployment, same routes, same pages.

Synchronization is purely additive — new columns and new tables, with no change to how existing queries work. Managers, administrators and owners see Club POS and Inventory data on the web because terminals and the LAN server push Class 1 facts to the cloud, and the existing admin and reporting screens already read those tables. Requirement 6 needs no new read screens.

## 11. Database and Migration Strategy

The 165 existing migrations are MySQL-hardened; `MIGRATION_RESOLUTION.md` records duplicate columns, duplicate indexes and the 64-character identifier limit. Both engines are already first-class in `config/database.php:34,46`. The current state is contradictory and is resolved in Phase 0: `README.md:65` claims SQLite, while `.env:23`, `.env.testing:18` and `MIGRATION_RESOLUTION.md` prove MySQL, and `phpunit.xml:26-27` runs tests on in-memory SQLite with `tests/TestCase.php:26-28` enforcing it.

Rules:

1. **Never rewrite an applied migration's effect.** Only make driver-specific code paths conditional, following the driver-aware pattern already established in `database/migrations/2026_04_10_160000_harden_inventory_ledger_tables.php`.
2. Run all 165 migrations fresh against Postgres and fix every failure.
3. Add a **schema parity test** comparing a fresh Postgres schema against MySQL — columns, types, nullability, indexes, foreign keys — as a permanent gate. Migrating without erroring is not evidence the schemas match.
4. All `sync_*` columns arrive via new migrations, never by editing existing files.

## 12. Phasing

Each phase ships something usable. The dependency chain is not negotiable: make the database portable, make the sync engine work headlessly, repair Club POS, then put a UI and an installer around it.

### Phase 0 — Unblock the ground truth

Nothing else can be trusted until this is done. Ordered by dependency, not by convenience.

- **0a — Make SQLite build the schema.** **Done as of Phase 0R.** The `fullText` blocker in `2026_04_12_000001_create_reporting_events_table.php` is now guarded by `if ($driver !== 'sqlite')`, and `migrate:fresh` completes all 165 migrations on SQLite with 0 failures. On MySQL and Postgres the fulltext index is unchanged. A real SQLite fulltext index would need an `fts5` virtual table plus sync triggers and matching query changes; that is a separate decision because no application query currently uses `MATCH` — verified across `app/`. This was first because terminals run migrations against SQLite and the test suite depends on it.
- **0a-bis — Stop the schedule running every job twice.** `routes/web.php:39` does `require __DIR__ . '/console.php'` alongside its sibling route files, while `bootstrap/app.php:11` also registers that file via `withRouting(commands:)`. The result is 32 registered schedule events for 16 real jobs, so every scheduled job — queue draining, revenue and occupancy reports, billing, night audit, cleanup — executes twice per tick. Remove the stray `require`. This is a live production defect, not documentation.
- **0b — Restore a green test suite.** With 0a done, drive the suite from 52 failed to zero, or failing that, to an explicitly documented and justified set of skips. Without a trustworthy suite, no later phase can prove it did not break anything.
- **0c — Remove dead files.** `app/Policies/GuestRequestPoicy.php` and `app/Services/BookingAccontingService.php` (both zero bytes, both misspelled, both unreferenced), and `database/migrations/cmb.py`, a 328-line Python development script committed inside the migrations directory. Note that no correctly-spelled `GuestRequestPolicy` exists either, so guest-request authorization is genuinely absent and must be specified rather than merely renamed.
- **0d — Delete the dead console kernel.** `app/Console/Kernel.php` is never registered by `bootstrap/app.php:9-13`. Verified entry-by-entry, `routes/console.php` is a strict superset — all sixteen identical entries plus the `inspire` command — so nothing needs porting. Delete the class. Correct the four documents that still send developers there to add schedule entries, where such edits would be silently ignored: `REPORTING_PHASE_2_SUMMARY.md:184`, `REPORTING_IMPLEMENTATION.md:226`, `REPORTING_COMPLETE.md:104`, `REPORTING_PHASE_3_COMPLETE.md:44`.
- **0e — Correct documentation that actively misleads.** See §12.2 for the enumerated false claims.
- **0f — Record the superseded architecture decision**, so the constraint in `technical layout.txt.txt:15,40-47,54-59` has a visible replacement rather than being quietly ignored.

### Phase 1 — Postgres portability

All 165 migrations run fresh against Postgres, driver-specific paths made conditional, schema parity test added as a permanent gate.

### Phase 2 — Sync engine, headless

`sync_nodes`, `sync_changes`, `sync_cursors`, `sync_conflicts`, the HLC implementation and trait, and the pure merge function with the five-class policy catalogue. Phase 2 is self-contained: the trait is exercised against the sync tables themselves, and no domain table is touched yet.

Tested as a library against two SQLite databases simulating two disconnected nodes. No UI, no installer, no desktop application. The hard thinking belongs here and is testable in isolation.

### Phase 3 — Repair Club POS

A prerequisite, not a side quest. Fix the defects in §2.3 and ship a working POS on the web, with the tests it currently lacks. This phase is only possible once Phase 0 has made the POS tables exist on SQLite.

### Phase 4 — Runtime and sync node role

Apply the Phase 2 trait to the domain tables — new migrations adding `sync_hlc`, `sync_origin_node_id` and `sync_deleted_at` to every Class 1, 2 and 4 table. Convert `InventoryService` to the movement-authoritative shape in §6.2, with the rebuild command and its `rebuild == incremental` test.

Add synchronization CLI commands and a scheduled worker, so a plain `artisan`-driven synchronization node runs on MySQL, Postgres or SQLite. Proven with three processes on one machine before any desktop work begins.

### Phase 5 — Booking authority partition

Implement §8. Enforce the 36-hour cutoff in both directions: the public booking engine closes reservations for arrivals inside the window and directs the guest to the front desk, and the front-desk booking form refuses arrivals beyond it and routes staff to the online channel. Store the cutoff as property-level configuration in `settings` (Class 5), not a hardcoded constant.

The two halves land in different places. The online half is enforceable immediately on the cloud and is worth shipping early. The front-desk half only becomes meaningful once a LAN server is authoritative for short-notice arrivals, so it is verified in Phase 7.

Ship the staff communication this requires in the same phase — this changes how the desk works.

### Phase 6 — Tauri application and setup wizard

The three roles, bundled FrankenPHP, embedded Postgres, discovery, pairing, and the Ziggy runtime route fix from §9.4, which blocks every node other than the cloud.

### Phase 7 — Offline terminal

Bundled SQLite runtime, snapshot pull, and an offline sale path proven by disconnecting the network mid-shift. Verify the front-desk half of the Phase 5 cutoff against a real LAN server here.

### Phase 8 — Cloud deployment

Coolify with Postgres, synchronization daemon, web version confirmed intact, manager visibility confirmed.

### 12.1 Decomposition

This design is not one implementation plan. It is nine phases spanning database portability, a distributed synchronization engine, a POS repair, a native application with two embedded runtimes, and a deployment change. Each phase is planned and executed separately, in order, with a committed and verified state at every boundary.

Phases 0 to 3 are worth planning together as one body of work, because none of them ship user-visible value alone and each is small. Phases 4 through 8 each need their own plan.

### 12.2 Enumerated documentation defects for Phase 0e

Every item below was verified against the code, not inferred.

**`README.md` — actively false:**

| Line | Claim | Reality |
|---|---|---|
| 11, 30 | "Flutterwave, Stripe, and PayPal" | Flutterwave and Paystack only. Stripe and PayPal appear solely as transitive entries in `composer.lock`. |
| 47 | "System Settings: Multi-tenant configuration" | No tenancy exists anywhere — no `users.property_id`, no tenant middleware, no scoped roles. `README.md:47` is aspirational. |
| 53 | "RESTful APIs: JSON endpoints for mobile and third-party integrations" | `routes/api.php` holds two payment webhook POSTs. |
| 65 | "SQLite — Development database" | Production is MySQL (`.env:23`), and this design moves it to Postgres. |
| 73 | "Inertia.js — Server-driven Vue SSR" | Inertia is not SSR. |
| 105 | "Database (SQLite/Production DB)" | Same driver confusion. |
| 134-138 | "RESTful API Architecture" section | Entirely false. |
| 146 | "`app/Http/Controllers/` — handlers (public, staff, admin, API)" | No `Api/` directory exists. |
| 162 | "SQLite (built-in)" listed as a prerequisite | SQLite cannot currently build the schema (§2.2). |
| 214 | `php artisan test tests/Feature/BookingTest.php` | That file does not exist. |
| 350 | "`api.php` — JSON API routes" | Two webhooks. |
| 369 | "API Design: RESTful endpoints with consistent patterns" | False. |
| 381 | "open-source software. Check the LICENSE file" | **No LICENSE file exists.** Publishing posture needs a decision, not a correction. |
| 394 | "100% test coverage with PHPUnit" | Suite is red at baseline: 52 failed, 1 passed, 78 pending. |
| 395 | "Zero-downtime database migrations" | Not implemented. |
| 397 | "Perfect for hotels, hospitality chains" | Contradicts the single-property design decision. |

**`.github/copilot-instructions.md` — false and internally contradictory:**

| Line | Claim | Reality |
|---|---|---|
| 7 | "Database: SQLite (development)" | Omits MySQL production and the Postgres target. |
| 10, 32, 96 | Routes include an API layer | Two webhooks only. |
| 22 | "`app/Http/Controllers/Api/` — RoomApiController, BookingApiController" | **The directory does not exist.** |
| 139 | "Use named migrations (not timestamps)" | All 165 are timestamped. Contradicts its own line 26. |
| 144 | "Web routes in `routes/web.php` (no API routes currently)" | Contradicts its own lines 10, 32 and 96. |
| 208 | "queue driver default: sync for testing" | `.env` sets `QUEUE_CONNECTION=redis`. |
| 211 | "`config/mail.php` (array driver for testing)" | `.env.example` sets `MAIL_MAILER=log`. |
| 231 | "Last Updated: December 2025" | Stale. |

**Documents claiming completion of work that is not complete:** `PRODUCTION_DEPLOYMENT_READY.md` ("APPROVED FOR PRODUCTION"), `REPORTING_COMPLETE.md` ("fully implemented, tested"), `DEPLOYMENT_READINESS.md:318-319` ("Approved By: [Manager Name Here]"). These mislead readers about production readiness and should be corrected or withdrawn.

## 13. Testing Strategy

Risk is concentrated in merge correctness, so that receives the attention.

- **Two-node property test** — random operation sequences against two disconnected databases, then synchronize both ways, asserting convergence. Runs on every policy change.
- **Policy catalogue unit tests** — explicit cases for each of the five classes, including the three-term case where Class 4 must block.
- **Idempotency test** — replay an entire change log twice; assert byte-identical state.
- **Ledger rebuild test** — `rebuild == incremental` for inventory and drink stock. Without it, §6.2's guarantee is unenforced.
- **Golden synchronization fixture** — a committed JSON scenario replayed on every engine, so SQLite and Postgres cannot drift.
- **Offline shift scenario** — a scripted shift on a terminal with the network cut, reconciled against a LAN server, asserting every sale and stock movement survives.

## 14. Risks

**Schema parity is the sleeper risk.** 165 migrations ported to a second engine is where this project is most likely to quietly diverge, and a missing index or a widened nullable column produces no error — only slow queries and bad data six months later. The parity test is the mitigation and is not optional.

**FrankenPHP packaging is the largest unknown.** One binary per platform with the correct extensions, supervised reliably, surviving operating system restarts. This is not a configuration task and needs real time.

**Embedded Postgres** on the server role is a dependency chosen for convenience. If it proves fragile on a target OS the fallback reintroduces an installer, which requirement 3 exists to avoid.

**Clock skew.** The HLC is what makes cross-node comparison safe, but it is subtle code written once and tested hard. Getting it subtly wrong produces silent data loss rather than an exception.

**SQLite portability was assumed, not verified.** Every terminal in this design runs migrations against its own SQLite file, and 24 migrations have never successfully run on SQLite (§2.2). The count of further failures behind that one is unknown until Phase 0a works through them. If they prove deep rather than shallow, the terminal sizing and the Phase 6 estimate both move.

**The 36-hour rule is a business change.** It must reach front-desk staff before it ships, or staff discover it the hard way.

**Bundled PHP means terminal migrations run repeatedly.** Each terminal runs all 165 migrations against its own SQLite file on first setup. Acceptable at three to six terminals; worth monitoring if that grows.

## 15. Out of Scope

- Multi-property data partitioning beyond protocol and node identity (§4.4).
- Full tenant isolation, per-property users, property-scoped roles.
- Staff-client local read caching (§4.1).
- Migrating off Hostinger before the Coolify path is proven.
- Offline guest room-service ordering. Guests already order over the public web, which requires internet.
- Provisional holds for far-out front-desk reservations (§8) — a clean later addition.
- Unrelated cleanup beyond Phase 0.

## 16. Open Items

These are deliberately unresolved and require input before the phase that depends on them.

1. **Receipt printing.** `PosPrintLog` exists but no print implementation does. Target hardware is unknown — thermal receipt printers, or reprint from a management screen. **Resolved in favour of thermal/ESC-POS via a local daemon bridge** (see Phase 7); the printer model and transport are still to be captured from the user and do not block the generic ESC/POS implementation.
2. **Embedded Postgres distribution.** Confirm the chosen embedded build and its licensing and platform coverage. **BLOCKING:** Phase 6 estimation is unreliable until this is answered.
3. **FrankenPHP extension packaging** for Windows specifically, which is the most constrained platform. **BLOCKING:** Phase 6 estimation is unreliable until this is answered.
4. **Firewall rule registration** during server setup may require elevation on Windows and macOS. If it cannot be done without prompting, setup must say so explicitly rather than failing silently.
5. **Image extension choice (GD vs Imagick).** **Resolved 2026-10-05: GD.** It ships inside the PHP runtime, so the Windows packaging surface is smaller. Recorded in §9.1.