# Terminal Synchronization Scope

What a terminal synchronizes, what it deliberately does not, and why. This
document, [`config/sync_scope.php`](../config/sync_scope.php) and
[`App\Sync\SyncScope`](../app/Sync/SyncScope.php) are one artifact in three
parts and must be changed together.

Spec reference: `docs/superpowers/specs/2026-10-04-desktop-lan-offline-sync-design.md`
§7. Phase: 4S (closes G26).

## Why a scope exists

A terminal is a Club POS or Inventory device working offline on a LAN. It does
not need the whole hotel database. It synchronizes the **transitive closure** of
what POS and Inventory actually read and write.

Getting this wrong is expensive in both directions:

- **Too small** — the terminal fails at the moment of sale, with no network to
  recover from. This is the failure that matters most.
- **Too large** — every sync ships tables nobody touched, wasting the LAN
  bandwidth the system exists to avoid.

So the scope is **derived from real code**, not from a table list, and a test
fails when the code reaches outside it.

## The guard

| Piece | Responsibility |
|---|---|
| `app/Sync/SyncScopeResolver.php` | Walks the real Eloquent graph from the POS entry points and returns the transitive table closure. |
| `tests/Feature/Sync/SyncScopeTripwireTest.php` | Fails when a reachable table is neither in the manifest nor recorded as excluded. |
| `tests/Feature/Sync/SyncScopeManifestTest.php` | Keeps the manifest minimal (no dead entries) and consistent with the policy catalogue. |
| `config/sync_scope.php` | The manifest itself. |

Entry points: `PosDocket`, `Booking`, `InventoryItem`, `MenuItem`, depth 3.

### Why the resolver does not call every method

Eloquent has no `Model::getRelationships()`. Invoking every public no-argument
method would run mutating accessors — `Booking::generateRoomAccessTokens()`
deletes and recreates rows. The resolver therefore treats a method as a
relationship **only when its own source body constructs an Eloquent relation**
(`hasMany`, `belongsTo`, … off `$this` or `$model`), and never executes
anything else.

### Boundaries

Traversal stops at `User` and `Property`. POS reads them for an actor or a
single-property binding, and does not follow them into unrelated modules
(staff messaging, room-service orders, maintenance). Following them would pull
half the hotel database into the closure and make the tripwire fire on changes
that are not POS dependencies.

### `always_sync`

A few tables are unambiguously in scope but are not reachable by following a
relation from the entry points — a receipt log is addressed by docket id, a
role assignment by user id. These are listed in `always_sync` with the reason
they are reached some other way. This keeps the inverse check honest: a manifest
table the resolver cannot reach is either recorded here or removed as dead
weight, never left as a false failure.

Current `always_sync` entries: `pos_print_logs`, `drink_stock`,
`drink_stock_movements`, `menu_inventory_recipes`, `staff_profiles`, `roles`,
`settings`, `booking_rooms`.

## What a terminal syncs

Policy classes are defined in spec §6 and implemented in
`app/Sync/Policies/`. The label in the manifest maps to the class in
`SyncScope::CATALOGUE`.

### Class 1 — Immutable facts (append-only, never merged)

`pos_dockets`, `pos_docket_items`, `pos_docket_payments`, `pos_print_logs`,
`inventory_movements`, `drink_stock_movements`, `charges`.

The sale itself, the stock ledger and the guest folio. Two nodes creating rows
of the same class both survive; no conflict is possible.

`charges` is the table `room_charge` writes. There is no `folio` or `ledger`
table — the ledger is `charges` plus `journal_entries`/`journal_lines`, and only
`charges` is needed to sell a drink. **Authoritative on both sides.**

### Class 2 — Mutable reference data (row-level last-write-wins)

`inventory_items`, `inventory_locations`, `pos_devices`, `menu_items`,
`menu_categories`, `menu_subcategories`, `menu_inventory_recipes`, `rooms`,
`room_types`, `staff_profiles`, `users`.

The higher `sync_hlc` wins; `sync_origin_node_id` breaks an exact tie. Authored
centrally, pushed down to terminals. **Cloud-authoritative in practice** — see
the read-only gate below.

### Class 3 — Derived balances (never authoritative, recomputed)

`inventory_stocks`, `drink_stock`.

Caches of a sum over Class 1 rows. Each node recomputes the sum in the same
deterministic total order, so there is nothing to reconcile and no conflict is
possible. An incoming balance is dropped, never merged.

### Class 4 — Hard conflicts (blocked, surfaced to a human)

`bookings`, `booking_rooms`, `pos_sessions`.

- Overlapping bookings on one room (reuses `RoomAvailabilityService`).
- Two open sessions on one physical device.
- A payment captured on one node and refunded on another.

Never auto-resolved. **Neither side authoritative** — a human decides.

### Class 5 — Cloud-authoritative configuration (pushed down, never contested)

`settings`, `roles`.

The 36-hour booking cutoff lives in `settings`. Roles are the entire
authorization model; there is no `permissions` table in active use. **Cloud
wins; terminals do not write.**

## The read-only gate

`config('sync_scope.read_only')` lists the tables a **terminal** may read but
must never emit. The cloud and the LAN server are unrestricted; only
`terminal` nodes are gated (`SyncScope::mayEmit`).

This is a correctness requirement, not a style preference. Without it a
terminal that saves a menu item — a stray edit, a failed import replay, an
over-eager admin action — emits a Class 2 change and fights the cloud over a
live price. Last-write-wins means whoever wrote last wins, so an offline
terminal with a stale clock can overwrite cloud configuration and **no conflict
is ever recorded**.

Read-only on a terminal: `menu_items`, `menu_categories`, `menu_subcategories`,
`menu_inventory_recipes`, `room_types`, `rooms`, `inventory_locations`,
`staff_profiles`, `users`, `roles`, `settings`.

`pos_devices` is deliberately **not** read-only: a terminal registers its own
device row locally at setup.

## What a terminal does not sync

Reached by the resolver but deliberately out of scope. Every entry in
`config/sync_scope.php` `excluded` carries its reason.

| Table | Why excluded |
|---|---|
| `orders`, `order_items`, `order_events` | Guest/room-service ordering; out of scope offline (spec §15). |
| `payments` | Folio posting writes `charges`; capture/refund is a cloud/LAN Class 4 concern, not an offline terminal path. |
| `discount_codes`, `discount_code_redemptions` | Promotions authored centrally; not a POS critical path. |
| `images`, `menu_item_images` | Static media cached at install. |
| `properties` | Single-property deployment; a node binds one property at setup (spec §4.4). |
| `room_access_tokens` | Door access, not POS or Inventory. |
| `room_cleanings` | Housekeeping, not POS or Inventory. |
| `room_payments` | Room booking payments, not POS or Inventory. |
| `journal_entries`, `journal_lines`, `accounting_periods` | Manager and accounting surface, not needed to sell a drink. |
| `events`, `event_tickets`, `galleries` | Not on a POS critical path / static reference data. |
| `jobs`, `failed_jobs` | Local infrastructure. |
| `audit_logs` | Local audit trail; each node keeps its own. |

### Reporting projections are excluded deliberately

`reporting_*` tables are derived from Class 1 rows (spec §6.1). Each node
rebuilds its own projections from synchronized source rows. Synchronizing
derived data would import **projection drift** — two nodes disagreeing about a
total because one rebuilt at a different moment.

## How to add a table

A table is added in one commit, all three parts together:

1. **Manifest** — add the table to `config/sync_scope.php` under `tables` with
   its policy label, and to `read_only` if a terminal must not emit it. If the
   resolver cannot reach it, add it to `always_sync` with the reason.
2. **Catalogue** — add the table to `SyncScope::CATALOGUE` with the matching
   policy class.
3. **This document** — add it to the right class above, and to the exclusion
   table if it is out of scope.

Run:

```powershell
php artisan test --filter='SyncScope'
```

If the resolver reaches the new table, the tripwire fails until step 1 is done.
That failure is the feature.

## The reference snapshot

Sync deliberately moves the tables above and nothing else. That leaves the
terminal without the data it must already hold the moment it is installed: the
staff accounts it authenticates, the roles and permissions those accounts
carry, the menu it sells from, and the settings and property it binds to.
`config('sync_scope.reference_snapshot')` is the second transport for exactly
that data. A newly installed terminal pulls it over `GET /api/sync/reference`
and re-pulls it with `php artisan sync:refresh-reference` when staff or menu
change later.

**It is a curated list, not a derivation of `no_capture_path`.** The two look
similar but answer different questions. `no_capture_path` records every
in-scope table that has no capture path, which includes transactional and
derived tables — `charges`, `booking_rooms`, `inventory_stocks`,
`drink_stock` — that must never be snapshotted, because replaying a balance or
a folio across installs is wrong. `reference_snapshot` is the subset that is
safe and necessary to copy whole, and each entry carries its own reason.

**The list is ordered for foreign keys.** The pull writes into a database where
SQLite enforces them, so every insert must find its parent already written.
`departments` is the worked example: it was missing from the first draft, and
without it — since `users.department_id` references it — a snapshotted user who
carries a department cannot be written at all. The applier walks the list in
declaration order, so the order is load-bearing rather than cosmetic.

**The payload carries password hashes, and that is accepted.** Login has to
work immediately after an install, with no password reset and no cloud
round-trip, so the `users` rows travel whole and a terminal authenticates
against its own copy. The consequence — a stolen terminal exposes hashes for
offline cracking — is a stated, accepted risk, mitigated by physical control of
floor machines; it is recorded in the deployment runbook, not hidden.

**`menu_inventory_recipes` fails on a first pull, by design.** It is declared
last on purpose: its `inventory_item_id` parent arrives by *sync*, not by
snapshot, so on a first pull into an empty database the table's transaction
rolls back while every other table lands. It completes on the next
`sync:refresh-reference`, once the synced inventory items are present. A
transaction-per-table applier makes this a clean, re-runnable partial rather
than a corrupt one.

## Known decisions and deviations

- **`model_has_roles` pivot is not included.** Spec §7 says "users and roles".
  Role assignments live in Spatie's `model_has_roles` pivot, which is not
  enumerated here. A terminal reads a user's roles for authorization (`hasRole`
  / `hasAnyRole`); it does not author them. If role assignment must travel to a
  terminal it is a deliberate follow-up, not an omission.
- **`roles` is Class 5, not Class 2.** The Phase 4S plan's manifest sample
  labelled `roles` as `lww`; spec §6 Class 5 lists it as cloud-authoritative.
  The spec wins: `roles` is `push_down`.
- **`pos_devices` was added** beyond the plan's table list: the resolver reaches
  it through `pos_sessions`, and the shift lifecycle needs it.
- **The plan's "prove it fails" example used `PosDocket::hasMany(Image::class)`.**
  `images` is already in the closure and is recorded as excluded, so it would
  **not** fail. The proof instead uses a table in neither list
  (`PosDocket::hasMany(GuestRequest::class)` → `guest_requests`), which the
  tripwire reports as `guest_requests (from PosDocket)`.
