# Reference-data snapshot — design

**Date:** 2026-10-19 · **Status:** approved, pending plan
**Scope:** getting centrally-authored reference data onto a newly installed
terminal, and keeping it there afterwards.

## Problem

A freshly installed terminal cannot log in and cannot sell.

`SyncEntityRegistry` holds exactly fifteen tables — `pos_dockets`,
`pos_docket_items`, `pos_docket_payments`, `pos_print_logs`, `pos_sessions`,
`inventory_movements`, `inventory_items`, `inventory_locations`,
`drink_stock_movements`, `payments`, `audit_logs`, `room_types`, `rooms`,
`staff_profiles`, `bookings` — and those are the only tables that ever travel
over sync. Everything a terminal needs to *be* a terminal has no capture path
and never arrives:

`properties` · `users` · `roles` · `permissions` · `settings` ·
`menu_items` · `menu_categories` · `menu_subcategories` ·
`menu_inventory_recipes` · `pos_devices`

`plan_for(SetupRole::Terminal)` is `create database → migrate → pull snapshot →
register`, and there is no `seed` step, so without the snapshot the local
database is empty of users, menu, roles and property. The wizard has known:
`setup/mod.rs:485` records *"the initial snapshot is pulled from the LAN server
once discovery lands (Task 6)"* — never built, and no endpoint exists for it.

The `CloudSyncIntegrationTest` papers over this by hand-copying these rows with
ids preserved, which is precisely the behaviour a real install needs.

A second gap compounds it: these tables have no capture path, so even once a
snapshot exists, a staff account or menu price added later would never reach
the floor.

## Decisions (made with the owner)

1. **Source: the LAN server first, the cloud as fallback.** A terminal pulls
   from its own LAN server when one is present — matching the wizard's recorded
   intent and keeping each site's reference data owned by that site — and falls
   back to the cloud for a terminal peered with it directly.
2. **Install pull plus a re-runnable refresh command.** The wizard pulls once at
   install; an operator (or the existing schedule) can invoke the same pull
   again later. Not a sync-engine change.
3. **Registration precedes the snapshot.** A consequence of the auth model below,
   not a preference: the credential the snapshot uses is minted at registration.

## Current state (verified at HEAD)

| Fact | Evidence |
|---|---|
| The terminal plan includes a snapshot step that does nothing | `setup/mod.rs:117-122` declares `STEP_SNAPSHOT`; `mod.rs:485` records it as not implemented |
| No snapshot endpoint exists | no `snapshot` match in `routes/api.php` or `app/Http/Controllers/Sync/` |
| Node auth requires a row on the **serving** node | `AuthenticateSyncNode` → `SyncNode::where('node_id', …)`, `status` must be `active` |
| The registry is exactly 15 tables | `SyncEntityRegistry::MAP`, documented as "exactly the tables in SyncScope that carry sync fields" |
| `properties` is in neither list | absent from the class table and from `no_capture_path`, yet `rooms.property_id` and `room_types.property_id` depend on it |
| `no_capture_path` mixes reference and transactional tables | `charges`, `booking_rooms`, `inventory_stocks`, `drink_stock` sit beside `users` and `menu_items` |
| The wizard seeds reference data only on the **server** role | `plan_for(SetupRole::Server)` runs `STEP_SEED` and `STEP_CREATE_ADMIN`; `Terminal` does not |

### The auth consequence

`AuthenticateSyncNode` resolves `X-Sync-Node-Id` against the local `sync_nodes`
table. A terminal can therefore only pull a snapshot from a node it is
registered with. Registration target and snapshot source are the same node,
which both settles the "LAN server first, cloud fallback" choice and forces the
wizard order change.

## Design

### Wizard order

```
create database → migrate → register → pull snapshot
```

Registration moves ahead of the snapshot because it is what mints the
credential. The snapshot pull runs against the node just registered with:
the LAN server when present, otherwise the cloud.

### Table set

A new curated list, `config('sync_scope.reference_snapshot')`:

```
properties, users, roles, permissions, role_has_permissions,
model_has_roles, model_has_permissions, settings,
menu_categories, menu_subcategories, menu_items,
menu_inventory_recipes, pos_devices
```

It is deliberately **not** derived from `no_capture_path`: that list also holds
`charges`, `booking_rooms`, `inventory_stocks` and `drink_stock`, which are
transactional or derived and must never be snapshotted.

Two tests guard the list:

- **No overlap with `SyncEntityRegistry::keys()`** — a table is either synced
  or snapshotted, never both. Duplicating the two paths would let ids arrive by
  two routes and race.
- **Every entry resolves to a real table with a declared primary key**, so a
  renamed table fails the suite instead of silently shipping nothing.

`properties` is pulled into `sync_scope` coverage by this work. It is currently
invisible to `SyncScopeManifestTest` because it is not declared at all, which is
also why a synced `room` can arrive at a node whose `properties` table is empty.

### Endpoint

`GET /api/sync/reference`, inside the existing `sync.node` middleware group —
same credential, same `last_seen_at` stamping, same throttling model. No new
authentication concept.

Response:

```json
{
  "generated_at": "2026-10-19T12:00:00Z",
  "tables": { "users": [ { "id": 1, "...": "..." } ] }
}
```

Rows are keyed by their primary key and serialised with columns explicit, so
the payload is engine-independent (the same MySQL/Postgres/SQLite concern that
drove `ReportController::hourExpression()`).

### Applying

- Idempotent **upsert by primary key, ids preserved** — everything references
  `users.id`, and `rooms` reference `properties.id`.
- A first pull into an empty database is a plain insert.
- **Never deletes implicitly.** `--prune` removes local rows absent from the
  snapshot, an explicit operator action.
- Applied inside a transaction per table so a failed pull leaves no half-written
  reference set.

### Refresh

`php artisan sync:refresh-reference [--peer=<node-id>] [--prune]` pulls and
applies the same payload. Order is explicit rather than incidental: it tries the
peer whose local `sync_nodes` row is `type = 'lan_server'` first, then any
`type = 'cloud'` peer, then anything else configured; `--peer` skips the
ordering and targets one node. Usable by hand, and safe to put on the existing
schedule.

## Failure handling

- **No reachable peer:** the terminal keeps whatever it has; the wizard reports
  the failure plainly rather than leaving an empty database unexplained.
- **Registration failed:** no credential, so no snapshot — the snapshot step is
  skipped rather than attempted unauthenticated.
- **Partial payload:** the transaction per table rolls back that table only; the
  pull is re-runnable.
- **Table missing from the payload:** leave the local table alone. The payload
  is authoritative only for what it contains.
- **Refresh over existing data:** upsert by id, so nothing is duplicated and no
  id is reassigned.

## Security consequence (accepted, stated deliberately)

Shipping `users` ships **password hashes** to every terminal. That is inherent
to a terminal authenticating against its own database — the alternative is a
materially different authentication model, which the owner declined in favour of
the snapshot. The consequence: a stolen terminal exposes hashes for offline
cracking. The mitigation is physical control of floor machines. This is recorded
as a known limitation in the deployment runbook, not treated as an internal
detail.

## Non-goals

- **Not** giving these tables a sync capture path. That is the architecturally
  complete fix and remains open as a larger engine change.
- No snapshot for the cloud or the LAN server — both seed themselves in the
  wizard (`STEP_SEED`, `STEP_CREATE_ADMIN`).
- No incremental/differential snapshot; the payload is small enough to replace.
- No encrypted-at-rest copy on the terminal beyond what the database already
  does.

## Testing

- Unit: the snapshot table set has no overlap with the registry, and every entry
  resolves — a test that fails when either list is edited carelessly.
- Feature: `GET /api/sync/reference` requires a valid node credential, rejects
  an inactive node, and returns every declared table with its rows.
- Feature: pull into an empty database, assert ids preserved and a login works
  against the snapshotted `users` row (the actual symptom being fixed).
- Feature: refresh twice → no duplicate rows; refresh with `--prune` → a row
  absent from the source is removed; without `--prune` → it survives.
- Feature: the wizard plan order is `register` before `pull snapshot`, pinned so
  it cannot silently revert.

## Exit criteria

- A terminal installed against a LAN server can log in and open the POS without
  any manual row copying.
- A terminal whose LAN server is absent registers with and pulls from the cloud.
- A staff account added later reaches a terminal via `sync:refresh-reference`.
- The snapshot table set and the sync registry are provably disjoint.
- No manual intervention, no hand-copied rows, no `assertTrue(true)` standing in
  for any of the above.
