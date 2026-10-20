# Reference Snapshot — Implementation Plan

**Spec:** `docs/superpowers/specs/2026-10-19-reference-snapshot-design.md` (approved)
**Date:** 2026-10-19
**Scope:** one endpoint, one applier, one command, one wizard reordering, plus the scope declaration they all read from.

## Context

Sync does not move the data a new terminal needs to log in and sell: `users`, `roles`/
`permissions` and their pivots, `settings`, `properties`, the whole `menu_*` set and
`pos_devices` are all outside the 15-entity registry (they are in `no_capture_path`, or —
in the case of `properties` — deliberately outside the manifest entirely). A freshly
installed terminal therefore comes up with no staff accounts, no menu and no property, and
the staff-client path hand-copies reference rows at boot as a stopgap
(`tests/Feature/Cloud/WebVersionUnchangedTest.php`). This plan builds the pull that replaces
that stopgap: `GET /api/sync/reference` on the serving node, applied locally by
`sync:refresh-reference`. Rewiring the staff-client path onto it is deliberately **not**
part of this plan — see Out of scope.

Everything below argues from the approved spec. Where the plan differs from the spec it says
so, and why.

**Baseline:** 418 passed / 2444 assertions (`composer test`, SQLite). Postgres has 2 known
failures (B10 `guest_requests.status`, B5/F8 applier `updated_at`) — unchanged by this work.
Rust baseline: `cargo test --manifest-path desktop\src-tauri\Cargo.toml`.

**Never:** `migrate:fresh`, `SyncCapture` on terminal-owned tables, edits to
`envfile.rs` / `postgres.rs`, changes to any Livewire screen or component.

---

## Deviations from the approved spec — declared up front

1. **The table set has 14 entries, not the spec's 13: `departments` is added.**
   The spec lists `properties, users, roles, permissions, role_has_permissions,
   model_has_roles, model_has_permissions, settings, menu_categories, menu_subcategories,
   menu_items, menu_inventory_recipes, pos_devices`. But `users.department_id` is a real
   foreign key (`2025_12_20_141206_add_department_id_to_users_table.php` → `foreignId(...)->nullable()->constrained()->nullOnDelete()`) and SQLite enforces foreign keys here
   (`DB_FOREIGN_KEYS` defaults to `true`). Any snapshotted user carrying a department would
   fail its insert and roll the whole `users` table back — taking the "log in works" exit
   criterion with it. `departments` is a parent of a snapshotted table, which is exactly the
   rule the spec already states for ordering, so this is a gap in the spec's list rather than
   a change of scope. The derived FK guard in Task 1 fails loudly if the entry is ever
   removed. Task 7 updates the spec's list to match.

2. **The spec's `properties` wording is imprecise, not load-bearing-wrong.** Its
   Current-state row says `properties` is "in neither list", naming *the class table and
   `no_capture_path`* — true, it is in neither of those. It is, however, recorded under
   `excluded` (`config/sync_scope.php:200`), which the Table-set section's "not declared at
   all" overlooks. Task 7 tightens that sentence. Nothing else in the spec changes.

---

## Task 1 — Declare the snapshot table set and guard it

**Objective:** `config/sync_scope.php` becomes the single source of truth for which tables
the snapshot carries, in dependency order, with a recorded reason for each — and a guard test
proves the set never collides with the sync registry.

**Files:**
- `config/sync_scope.php` (edit)
- `app/Sync/TableKeys.php` (new)
- `tests/Feature/Sync/ReferenceSnapshotScopeTest.php` (new)

**Steps:**

1. Write `tests/Feature/Sync/ReferenceSnapshotScopeTest.php` first. It fails red on two
   counts: `App\Sync\TableKeys` does not exist yet (fatal at the first call), and
   `config('sync_scope.reference_snapshot')` is null, so the "non-empty set" assertion fails.
   Step 2 and step 4 clear both.

   The test asserts, in order:

   - the key exists, is non-empty, and every entry has a non-blank reason string;
   - **disjointness**: `array_keys(config('sync_scope.reference_snapshot'))` ∩
     `SyncEntityRegistry::keys()` is empty — two transports racing over one primary key;
   - every declared table `Schema::hasTable()` and yields a non-empty primary key from
     `TableKeys::primary()`;
   - **every foreign-key parent is available — derived, not hand-listed.** For each snapshot
     table, `Schema::getForeignKeys($table)` must report a `foreign_table` that is either
     itself in the snapshot or in `SyncEntityRegistry::keys()` (that is how
     `menu_inventory_recipes` → `inventory_items` is satisfied). A parent in neither set
     fails with a message naming both routes. **This is the assertion that catches
     `departments`**, which `users.department_id` references
     (`2025_12_20_141206_add_department_id_to_users_table.php`, `->constrained()`);
   - **order, also derived:** where a snapshot table's FK parent is itself in the snapshot,
     the parent must be declared first, so an insert into an empty, FK-enforcing database
     finds its parent already written. No hand-maintained pair list to go stale;
   - `properties` **is** snapshotted and is **not** in the registry;
   - the transactional/derived tables `charges`, `booking_rooms`, `inventory_stocks`,
     `drink_stock` are **not** snapshotted — this is what makes the set curated rather than
     derived from `no_capture_path`;
   - `menu_inventory_recipes` is declared last, because its `inventory_item_id` parent
     arrives by sync rather than by snapshot.

   Use `use RefreshDatabase;` — the test inspects real schema.

2. Add `reference_snapshot` to `config/sync_scope.php` immediately after the
   `no_capture_path` array (line ~130), before the `Node Types` comment block. Declared in
   dependency order, each entry keyed by table name with a string reason:

   ```php
   'reference_snapshot' => [
       'properties' => 'A synced room and room type reference it, and a terminal binds itself to one.',
       'departments' => 'FK parent of users.department_id; without it a snapshotted user with a department cannot be written.',
       'roles' => 'Authorization: the live path is Spatie HasRoles, so a user arrives without the role assigned to it.',
       'permissions' => 'Authorization: role_has_permissions and model_has_permissions both reference it.',
       'menu_categories' => 'Menu catalogue parent; menu_items.menu_category_id is a non-null FK.',
       'menu_subcategories' => 'Menu catalogue parent; referenced by menu_items and itself FKs to menu_categories.',
       'menu_items' => 'The menu a terminal sells from; depends on both catalogue tables above.',
       'users' => 'Staff accounts; a terminal authenticates against its local copy, which is why the payload carries password hashes.',
       'model_has_roles' => 'Spatie role assignment; without it a snapshotted user has no role.',
       'model_has_permissions' => 'Spatie direct permission assignment.',
       'role_has_permissions' => 'What each role is allowed to do.',
       'settings' => 'Cloud-authoritative configuration, including the booking cutoff.',
       'pos_devices' => 'POS device registry resolved by code; a terminal must resolve its own device.',
       'menu_inventory_recipes' => 'Declared last on purpose: its inventory_item_id parent arrives by sync, so this table alone rolls back on a first pull and completes on the next.',
   ],
   ```

   Header comment states: these tables are carried by `GET /api/sync/reference`; sync cannot
   move any of them; the list is **deliberately not derived from `no_capture_path`**, which
   also holds transactional and derived tables; declared in dependency order because the
   pull writes into a database where foreign keys are enforced
   (`DB_FOREIGN_KEYS` defaults to true on sqlite); and that `ReferenceSnapshotScopeTest`
   proves disjointness with `SyncEntityRegistry`.

3. In the same file, amend the `excluded` entry for `properties` (line 200) so the reason
   matches reality: it stays excluded from sync (single-property deployment) but is **served
   by the reference snapshot**, because synced rooms and room types reference it.

4. Create `app/Sync/TableKeys.php`:

   ```php
   class TableKeys
   {
       /** @return list<string> */
       public static function primary(string $table): array
       {
           foreach (Schema::getIndexes($table) as $index) {
               // SQLite reports `primary` from `origin = 'pk'` as 0/1, the other
               // grammars as a boolean: this is a truthy check on purpose.
               if (! ($index['primary'] ?? false)) {
                   continue;
               }

               return array_values($index['columns']);
           }

           throw new RuntimeException(
               "Table [{$table}] declares no primary key; a snapshot row cannot be upserted by id."
           );
       }
   }
   ```

   Read from the schema rather than declared in config so a key that changes is picked up
   instead of drifting silently out of step with the data. `Schema::getIndexes()` is
   verified in vendor to return `list<array{name, columns: list<string>, type, unique,
   primary}>` on SQLite, Postgres and MySQL.

**Verification:**

```powershell
php artisan config:clear
php artisan test --filter=ReferenceSnapshotScopeTest      # green
php artisan test --filter=SyncScopeManifest              # unchanged
php artisan test --filter=SyncScopeTripwire              # unchanged
```

Also confirm no existing test enumerates `config/sync_scope.php` keys — `SyncScopeManifestTest`
reads `tables`, `always_sync`, `no_capture_path` and `read_only` by name, so a new sibling key
is inert.

**Commit:** `feat: declare the reference snapshot table set with scope guards`

---

## Task 2 — Serve the snapshot

**Objective:** `GET /api/sync/reference` returns the declared tables as
column-named rows, ordered by primary key, to an authenticated peer.

**Files:**
- `app/Sync/ReferenceSnapshot.php` (new)
- `app/Http/Controllers/Sync/ReferenceSnapshotController.php` (new)
- `routes/api.php` (edit)
- `tests/Feature/Sync/ReferenceSnapshotApiTest.php` (new)

**Steps:**

1. Write the test first. `Http::fake` is not needed — this is a local endpoint. Reuse the
   `NodeRegistrationTest` header shape (`X-Sync-Node-Id`, `Authorization: Bearer …`) and
   seed a `SyncNode` row directly with `credential => hash('sha256', $token)` (that is what
   `AuthenticateSyncNode` compares against).

   Cases:
   - no credential → 401;
   - `status !== 'active'` → 403;
   - a valid active node → 200, and `array_keys($json['tables'])` **===**
     `array_keys(config('sync_scope.reference_snapshot'))` — declared order, no more, no
     fewer;
   - rows are keyed by column name, not position: insert a `User::factory()` and assert
     `password` and `id` are present as keys, and that every row in `tables.users` has the
     same key set as the first (the applier in Task 3 depends on this invariant).

2. `app/Sync/ReferenceSnapshot.php`:

   ```php
   public function build(): array
   {
       $tables = [];

       foreach ($this->tables() as $table) {
           $tables[$table] = $this->rowsFor($table);
       }

       return [
           'generated_at' => now()->toIso8601String(),
           'tables' => $tables,
       ];
   }
   ```

   `tables()` returns `array_keys(config('sync_scope.reference_snapshot', []))`.
   `rowsFor()` orders by every column from `TableKeys::primary($table)` and returns
   `(array) $row` per row. Column names rather than positional arrays because the payload
   crosses an engine boundary (Postgres LAN server → SQLite terminal), where column order is
   not an agreement.

3. `ReferenceSnapshotController` is an invokable controller taking `ReferenceSnapshot` from
   the container and returning `response()->json($snapshot->build())`. Its class docblock
   records the payload implication: it carries `users.password` hashes, which is inherent to
   a terminal authenticating against its own copy, and is why the route sits behind
   `sync.node` like every other node-to-node endpoint.

4. `routes/api.php` — add inside the existing `Route::middleware(['sync.node'])->prefix('sync')->group(...)`:

   ```php
   Route::get('/reference', ReferenceSnapshotController::class);
   ```

   No new route group, no public route. `WebVersionUnchangedTest` asserts behaviour, not a
   route inventory, so this addition does not touch it.

**Verification:**

```powershell
php artisan test --filter=ReferenceSnapshotApiTest
php artisan test --filter=NodeRegistrationTest
php artisan test --filter=WebVersionUnchangedTest
```

**Commit:** `feat: serve the reference snapshot at GET /api/sync/reference`

---

## Task 3 — Apply the snapshot

**Objective:** write a peer's payload into this node, table by table, through the query
builder, reporting per-table failures without failing the whole pull.

**Files:**
- `app/Sync/ReferenceSnapshotApplier.php` (new)
- `tests/Feature/Sync/ReferenceSnapshotApplyTest.php` (new)

**Steps:**

1. Test first. The non-negotiable assertions:

   - **rows land.** Build a payload containing a `properties` row and a `menu_categories`
     row; after `apply()` both exist locally.
   - **passwords are not re-hashed.** Put a known hash (from `Hash::make('secret')`) into a
     `users` row; after `apply()`, `Hash::check('secret', $user->password)` is true. This is
     the assertion that fails if anyone routes the write through the `User` model and its
     `setPasswordAttribute` mutator.
   - **no capture.** After applying a `users` row and a `settings` row, the local sync
     change log has no new capture entries for those tables (terminal is `read_only` for
     them). Assert against the same change-log table `ChangeLogCaptureTest` inspects.
   - **a failing table does not stop the rest.** Payload with a good `roles` row and a
     `menu_inventory_recipes` row pointing at a non-existent `inventory_item_id`; after
     `apply()`, the role exists, `failed['menu_inventory_recipes']` is set, `applied` has
     the role, and the recipes table has no partial row.
   - **applying twice changes nothing.** Apply the same payload twice; row counts per table
     are identical after the second pass and the primary key values are the first pass's —
     no duplicates, no re-keyed ids. (Spec §Testing: "refresh twice → no duplicate rows".)
   - **unknown tables in the payload are ignored.** Include `charges` (and a made-up
     `not_a_table`) in the payload; neither is written. The applier iterates **config
     keys**, never payload keys — that is the security property.
   - **`--prune` only when asked.** With `prune=false`, a local row absent from the payload
     survives; with `prune=true` it is deleted, and a row still present in the payload is
     not.
   - **permission cache is forgotten.** Apply a payload containing a `permissions` row the
     node did not have; afterwards `app(PermissionRegistrar::class)->getPermissions()`
     contains it. Without the explicit forget, Spatie keeps resolving from the cache this
     node had before the pull.

2. `app/Sync/ReferenceSnapshotApplier.php`:

   ```php
   public function apply(array $payload, bool $prune = false): array
   ```

   Returns `['applied' => array<string,int>, 'failed' => array<string,string>]`.

   - iterate `array_keys(config('sync_scope.reference_snapshot', []))` — never the payload's
     keys;
   - skip a table the payload does not carry (absence says nothing about the local copy);
   - each table runs inside `DB::transaction(fn () => $this->write(...))`, `catch (Throwable)`
     records into `failed` and moves on;
   - `write()` uses `DB::table($table)->upsert($rows, $keys, $update)` — **query builder, not
     Eloquent**: a model event would be captured as a sync change on a `read_only` table, and
     `User::setPasswordAttribute` would re-hash an already-hashed password and lock every
     imported account out of the terminal;
   - `$keys = TableKeys::primary($table)`; `$columns` = schema columns ∩ first row's keys;
     every row is re-keyed to `$columns` (they arrive from one `SELECT`, so they already
     agree — this drops columns the local schema lacks);
   - `$update` = columns minus keys; when that is empty (Spatie's
     `model_has_roles`/`role_has_permissions` are entirely key) fall back to all columns —
     Laravel needs a non-empty update list to emit SQL, and it is an identical write;
   - `--prune` deletes local rows whose key signature is absent from the payload, inside the
     same transaction. Default (`false`) never deletes anything;
   - after the loop, `app(PermissionRegistrar::class)->forgetCachedPermissions()` guarded by
     `class_exists`.

   Class docblock records both reasons for the query builder (capture + password
   re-hash) and the per-table-transaction rationale.

**Verification:**

```powershell
php artisan test --filter=ReferenceSnapshotApplyTest
php artisan test --filter=ChangeLogCaptureTest
php artisan test
```

**Commit:** `feat: apply reference snapshots table-by-table without rehashing or recapturing`

---

## Task 4 — Pull command

**Objective:** `sync:refresh-reference` walks configured peers in preference order, pulls
the first answer, applies it, and reports honestly.

**Files:**
- `app/Sync/HttpSyncTransport.php` (edit — add `reference()`)
- `app/Console/Commands/SyncRefreshReference.php` (new)
- `tests/Feature/Sync/SyncRefreshReferenceCommandTest.php` (new)

**Steps:**

1. Test first. Seed `config(['sync.enabled' => true, 'sync.node_id' => …, 'sync.credential' => …])`
   in `setUp()` and use `Http::fake`.

   Cases:
   - `sync.enabled === false` → warns, exit 0 (nothing to do, not an error);
   - no peers configured → error, exit 1;
   - **preference order:** configure `sync.peers` as
     `['node-cloud' => ['base_url' => 'https://cloud.test'], 'node-lan' => ['base_url' => 'http://lan.test']]`
     and insert local `sync_nodes` rows with `type => 'cloud'` and `'lan_server'`. Fake both
     endpoints; assert the **first** request went to `lan.test` and that only one request was
     made (the first answer wins, so there is nothing for the fallback to do);
   - `--peer=node-cloud` (and separately `--peer=https://cloud.test`) reaches only that peer;
   - first peer refuses (`Http::fake` closure `throw new ConnectionException(...)`, the shape
     already used in `WebVersionUnchangedTest`) → falls through to the second, exit 0;
   - every peer unreachable → exit 1 with a message naming the peer;
   - **nothing applied** → payload containing only `['users' => [['id' => 1]]]` (SQLite
     `NOT NULL` on `users.name` rejects it deterministically, no FK dependency) → `applied`
     empty, `failed` non-empty → exit 1;
   - **partial failure still succeeds** → payload with a good `users` row and a bad
     `menu_inventory_recipes` row → exit 0, user present locally, warning printed.
   - **the symptom, end to end** (spec §Testing): with the local tables empty, fake a
     payload carrying a `users` row whose `password` is a hash of `'secret'` plus a
     `settings` row; run the command; assert the row's primary key came back **unchanged**
     (ids preserved) and that `Auth::attempt(['email' => …, 'password' => 'secret'])` is
     true — a login works against the snapshotted user, which is the actual defect being
     fixed. Then fake a second payload that **adds** a staff account, run the command again,
     and assert both accounts are present with no duplicates: the exit criterion "a staff
     account added later reaches a terminal via `sync:refresh-reference`".

2. Add to `HttpSyncTransport`:

   ```php
   /** @return array{generated_at?: string, tables?: array<string, list<array<string, mixed>>>} */
   public function reference(string $peer): array
   {
       return $this->send($peer, 'GET', '/api/sync/reference', []);
   }
   ```

   Reuses `send()`'s headers, timeout and `SyncTransportException` handling unchanged.

3. `app/Console/Commands/SyncRefreshReference.php`:

   ```
   sync:refresh-reference {--peer= : Pull from one peer by id or base URL} {--prune : Delete local rows the source no longer has}
   ```

   `handle(HttpSyncTransport $transport, ReferenceSnapshotApplier $applier): int`.

   Flow: `config('sync.enabled')` gate (warn + `SUCCESS`) → resolve peers → loop →
   `$transport->reference($baseUrl)` in `try/catch` (warn `unreachable: {peer} ({reason})`,
   continue) → `$applier->apply($payload, (bool) $this->option('prune'))` → `info('Pulled {n}
   rows across {m} tables from {peer}.')` → per-table warnings → return `SUCCESS` on the first
   peer that yielded data. Exit `FAILURE` only when no peer answered or nothing could be
   applied.

   `resolvePeers()` is a private method on the command:

   - walk `config('sync.peers')` — already normalised by `config/sync.php` to
     `array<node_id, ['base_url', 'credential']>`, with a bare `SYNC_PEERS=<url>` keyed by
     the URL itself;
   - apply `--peer` as a match on **either** the node id or the base URL (mirroring
     `SyncCycleRunner::resolvePeers`'s `$onlyPeer` handling);
   - when no `--peer`, rank by the local `sync_nodes.type`: `lan_server` → 0, `cloud` → 1,
     anything else (or no row at all) → 2, using `uksort` — PHP 8 sorts stably, so ties keep
     the configured preference order. `config/sync.php` documents peers as "ordered by
     preference" already, so for a terminal with a single `SYNC_PEERS` entry this ranking is
     a no-op.

   `SyncCycleRunner::resolvePeers` is `private` and applies no role preference, so this is a
   second implementation on purpose — the two rules are not the same. Note that in the
   method's docblock so the duplication reads as deliberate.

4. Class docblock explains: what the command moves that sync cannot; why the first answer
   wins; and why a per-table failure warns instead of failing — `menu_inventory_recipes`
   cannot be written until the inventory items it points at arrive by sync, and failing an
   install where every account and menu item landed would be a worse lie than a warning.

**Verification:**

```powershell
php artisan test --filter=SyncRefreshReferenceCommandTest
php artisan test --filter=TransportTest
php artisan test --filter=ReferenceSnapshot
php artisan test
```

**Commit:** `feat: add sync:refresh-reference pull command`

---

## Task 5 — Registration accepts the desktop's persisted node id

**Objective:** the wizard registers with the `SYNC_NODE_ID` it already minted and persisted,
so the local identity and the registry entry cannot diverge.

**Files:**
- `app/Http/Controllers/Sync/NodeRegistrationController.php` (edit)
- `tests/Feature/Sync/NodeRegistrationTest.php` (edit — three new tests)

**Steps:**

1. Test first, appended to `NodeRegistrationTest` (import `Illuminate\Support\Str`):

   - `test_the_desktop_can_register_with_the_node_id_it_already_persisted` — payload plus
     `node_id => (string) Str::ulid()`; assert 201, `node_id` in the response, and
     `SyncNode::sole()->node_id === $nodeId`;
   - `test_a_node_id_already_issued_is_rejected_without_spending_the_code` — register once
     with a node id, then redeem a **second** code with the same `node_id`; assert the
     uniform 422 (`'Invalid or expired pairing code.'`), `SyncNode::count() === 1`, then
     **re-run the second payload with a fresh node id and assert it now succeeds** —
     proving the rejection did not burn the code;
   - `test_a_malformed_node_id_is_rejected` — `node_id => 'not-a-ulid'` → 422 with
     `assertValidationErrors(['node_id'])`, and no `sync_nodes` row.

   Watch the `throttle:10,1` on the route: the second test makes three requests.

2. Controller edits:

   - add to `validate()`:

     ```php
     // The desktop has already minted and persisted a node id (envfile.rs mints
     // it once, beside the data, and it must never change); re-minting here
     // would leave the two identities disagreeing for the life of the install.
     // Optional, so `sync:register` — an operator approving a node by hand —
     // keeps working unchanged.
     'node_id' => ['sometimes', 'string', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
     ```

   - inside the transaction, **before** the pairing code is spent, reject a duplicate:

     ```php
     if (isset($validated['node_id'])
         && SyncNode::where('node_id', $validated['node_id'])->exists()) {
         // Checked before the code is spent: a rejected redemption must not burn
         // the single-use code, and taking an id this node already issued would
         // let a caller mint the credential belonging to an existing peer.
         return $this->rejected();
     }
     ```

     Validation alone cannot catch this — `sometimes` + `regex` passes a well-formed id that
     some other node already owns, and `node_id` is presumably the primary/unique key on
     `sync_nodes`, so `create()` would otherwise throw.
   - change the create to `'node_id' => $validated['node_id'] ?? (string) Str::ulid()`.
     `Str` should already be imported for the existing mint; verify.

**Verification:**

```powershell
php artisan test --filter=NodeRegistrationTest
php artisan test --filter=NodePairingCommandsTest
php artisan test
```

**Commit:** `feat: let a desktop pair with the node id it already persisted`

---

## Task 6 — Wizard registers, then pulls

**Objective:** terminal setup runs `create_database → migrate → register → snapshot`, emits
real events for both steps, and carries the credential into the process environment of the
snapshot pull.

**Files:**
- `desktop/src-tauri/Cargo.toml` (edit — TLS)
- `desktop/src-tauri/src/setup/mod.rs` (edit)
- `desktop/src-tauri/src/setup/phpcli.rs` (read only — `PhpCli::artisan(&self, app_root, args, env)`)

**Steps:**

1. **Cargo.toml.** `reqwest = { default-features = false, features = ["blocking"] }` has no
   TLS backend and no JSON helper. Add `"rustls-tls"`:

   ```toml
   # `default-features = false` drops the TLS backend too; without it a terminal
   # cannot reach an https peer, so the cloud fallback would fail to connect.
   reqwest = { version = "0.12", default-features = false, features = ["blocking", "rustls-tls"] }
   ```

   Confirm the existing `version` field before editing — do not change it.

2. **Plan order.** In `plan_for(SetupRole::Terminal)` (lines 117–122), swap the last two
   entries so the order is `create_database → migrate → register → snapshot`.

3. **`run_terminal` (line 438).** Replace the two deferred `Skipped` reports (lines 480–496)
   with:

   - `report(STEP_REGISTER_NODE, StepStatus::Started, None)`
   - resolve the server URL from `options.server_url` — `trim`, reject empty, strip a
     trailing `/`;
   - read `SYNC_NODE_ID` from `env` (add a small `env_value(&env, "SYNC_NODE_ID")` helper);
     if absent or empty, fail with "this install has no persisted node id to register";
   - build the body with a testable `registration_body(node_id, node_name, property_code,
     pairing_code)` helper that parses `property_code` as `i64` (a non-numeric code fails
     **before** any network call) and encodes:

     ```json
     {"property_code": 1, "pairing_code": "…", "node_name": "…", "node_type": "terminal", "node_id": "…"}
     ```

   - `node_name` comes from a `default_node_name()` helper: `COMPUTERNAME` or `HOSTNAME`
     trimmed and non-empty, else `"terminal"`. The wizard collects no name for a terminal;
     a recognisable name in the registry beats the literal string "terminal".
   - `register_with(server_url, body)` builds a `reqwest::blocking::Client` with
     `Duration::from_secs(30)`, POSTs with `CONTENT_TYPE: application/json`, checks
     `status.is_success()`, parses with `serde_json::from_str::<Value>` (no `.json()` —
     the feature is off), and returns `payload["credential"]` as `String`. Failure messages
     must include the HTTP status and the response text, because a 422 means *the pairing
     code was refused* and that is exactly what the operator needs to see.
   - On success: `state.mark_complete(STEP_REGISTER_NODE)` and
     `report(STEP_REGISTER_NODE, StepStatus::Succeeded, Some("paired with {url}"))`.
   - On failure: report `STEP_REGISTER_NODE` `Failed` with the reason, report `STEP_SNAPSHOT`
     `Skipped` with "registration did not complete, so there is no credential to pull with",
     then `return Err(error)` — consistent with how `run_lan_server` reports a failed step.
     The events are emitted through `progress` before the `Err` returns, so the window still
     shows both.

4. **Environment for the pull.** `envfile::render()` runs before registration (line 457) and
   `.env` is written from it before migrate (line 464), so the credential has to be written a
   **second** time once the server has issued it — and `env` re-parsed, because the process
   environment is what `PhpCli::artisan` passes through.

   ```rust
   // After a successful registration:
   rendered.push_str(&registration_env(&credential, &server_url));
   fs::write(&env_file, &rendered)
       .with_context(|| format!("failed to write {}", env_file.display()))?;
   let env = parse_env(&rendered);
   ```

   With `registration_env(credential, server_url) -> String` a tiny helper returning
   `"\nSYNC_CREDENTIAL={credential}\nSYNC_PEERS={server_url}\n"`, tested on its own.
   `rendered` is already `mut` (line 457); `env` is re-bound with a second `let`, so no
   existing binding needs to change. This is
   why `envfile.rs` needs no change: the wizard already appends `LAN_SERVER_URL` the same way
   at line 458. `SYNC_PEERS` is what `config/sync.php` reads to know who to pull from, and
   `config('sync.peers')` normalises a bare URL into a one-entry map keyed by that URL.

5. **The pull.** Report `STEP_SNAPSHOT` `Started`, run
   `php.artisan(&paths.app_root, &["sync:refresh-reference"], &env)`:
   - `Ok(output)` → `mark_complete(STEP_SNAPSHOT)` and report `Succeeded` with a summary
     taken from stdout (a `snapshot_summary(&output)` helper that finds the line starting
     with `Pulled `, falling back to a neutral `"snapshot pull finished"` — do not claim
     rows landed when the command printed nothing);
   - `Err(error)` → report `Failed` with `error.to_string()` and `return Err(error)`.
     `PhpCli::artisan` already bails with stdout and stderr on a non-zero exit, so a refused
     pairing code or an unreachable server reaches the screen with its own message.

6. **Comments to update:** the `TerminalOptions` docblock (lines 191–202) and the `pub fn
   run_terminal` comment both still say the snapshot and registration are deferred to
   discovery/Task 6. Rewrite both to describe the register-then-pull order.

7. **Tests** (in the existing `mod tests` block at line 771):

   - **update** `terminal_and_client_have_their_own_plans` (line 811) — it pins the old
     order. Assert the terminal plan is exactly
     `create_database, migrate, register, snapshot` (in that order) and leave the client
     assertions as they are.
   - **add** a test that `registration_body` produces the expected JSON, including
     `node_type == "terminal"` and the supplied `node_id`.
   - **add** a test that `registration_body` rejects a non-numeric `property_code`.
   - **add** a test that `default_node_name()` never returns an empty string.
   - **add** a test that the credential survives into the process env: render, append
     `registration_env`, parse, assert `SYNC_CREDENTIAL` and `SYNC_PEERS` are present and
     that `SYNC_NODE_ID` is unchanged by the append.

   Keep these unit-level; no network in tests.

**Verification:**

```powershell
cargo test --manifest-path desktop\src-tauri\Cargo.toml
cargo clippy --manifest-path desktop\src-tauri\Cargo.toml -- -D warnings
```

**Commit:** `feat: register the terminal then pull its reference snapshot`

---

## Task 7 — Reconcile the spec, document the scope change

**Objective:** the approved spec needs one addition and one precision fix, both verified
against the code above; and the new scope key must be documented where operators look.

**Files:**
- `docs/superpowers/specs/2026-10-19-reference-snapshot-design.md` (edit)
- `docs/SYNC_SCOPE.md` (edit)

**Steps:**

1. **Spec, Table set (lines 82–105).** Two edits:

   - add `departments` to the list, so the spec's 13 become the 14 that ship, and add a
     one-line note under it: *`departments` was missing from the first draft; it is the
     foreign-key parent of `users.department_id`, which SQLite enforces, so without it a
     user carrying a department cannot be written.* This is Deviation 1 from the Deviations
     section above — record it in the spec rather than leaving it only in this plan.
   - replace *"It is currently invisible to `SyncScopeManifestTest` because it is not
     declared at all"* with: *it is recorded only under `excluded`*
     (`config/sync_scope.php:200`), *which `SyncScopeManifestTest` does not read — the
     actual reason it is invisible to that test.* The Current-state row saying `properties`
     is "in neither list" (class table, `no_capture_path`) is correct and stays untouched.

   Nothing else in the spec changes.

2. Add a `## The reference snapshot` section to `docs/SYNC_SCOPE.md`, before
   `## Known decisions and deviations`, covering: what the snapshot is for; that
   `reference_snapshot` is a curated list and **not** a derivation of `no_capture_path`;
   the dependency-ordering rule (with the `departments` parent named as the worked example);
   that the payload carries password hashes and why that is
   accepted; and that `menu_inventory_recipes` legitimately fails on a first pull into an
   empty database and completes on the next `sync:refresh-reference`.

**Verification:**

```powershell
composer test          # full suite, must be green
git status             # no scratch files
```

Run `vendor\bin\pint` **only** on the PHP files this plan touched — Pint reformats unrelated
drifted files in this repo (notably `BookingService.php`, which is drifted at HEAD).

**Commit:** `docs: add departments to the snapshot table set and document it in SYNC_SCOPE`

---

## Exit criteria (spec §Exit criteria)

| Criterion | Where it is demonstrated |
|---|---|
| Terminal installed against a LAN server can log in with no manual row copying | Task 4's end-to-end test (pull → ids preserved → `Auth::attempt` true) plus Task 6's wiring of the pull into the wizard. The *installed* proof — log in and open a POS on a machine installed by the wizard — is a clean-VM check and stays with the owner's runbook, as Phase 8 R1 requires. |
| A terminal whose LAN server is absent registers with and pulls from the cloud | Pull: Task 4's fall-through test. Registration: `NodeRegistrationController` is server-agnostic — `node_type` is `lan_server`/`terminal`, and the serving node's own type is irrelevant — so pointing the wizard at the cloud URL pairs there. **Registration itself has no multi-peer fallback; only the pull does.** If the wizard's `server_url` is down the wizard reports the failure plainly (spec §Failure handling) rather than silently choosing another host. |
| A staff account added later reaches a terminal via `sync:refresh-reference` | Task 4's second-pull assertion (both accounts present, no duplicates). |
| Snapshot set and sync registry provably disjoint | Task 1's `ReferenceSnapshotScopeTest`. |
| No `assertTrue(true)` standing in for any of the above | Each task's verification block runs the named tests; Task 7 runs the whole suite. |

## Schedule

The command is **not** added to the scheduler in this plan, even though spec §Refresh says it
is safe to put there. It is idempotent, but every run overwrites local rows from the peer, and
a scheduled overwrite of rows an operator just edited is a decision for the owner, not for
this change. `SyncScheduleGuardTest` asserts the current schedule and is left untouched — if
the owner later wants it scheduled, that test is where the change is visible.

## Order and dependencies

| # | Task | Depends on |
|---|------|-----------|
| 1 | Declare table set + guards | — |
| 2 | Serve the snapshot | 1 |
| 3 | Apply the snapshot | 1 |
| 4 | Pull command | 2, 3 |
| 5 | Registration `node_id` | — |
| 6 | Wizard register-then-pull | 4, 5 |
| 7 | Docs | all |

Tasks 4 and 5 are independent of each other and can be interleaved. Nothing here touches a
Livewire screen, component, view or the sync capture cycle, so web behaviour is unchanged by
construction (Phase 8 R2: never modify a screen, component or view).

## Out of scope — recorded, not forgotten

- **Re-running terminal setup.** `run_terminal` truncates `hotelms.sqlite`
  (`fs::write(&db_file, "")`, line 462) and re-renders `.env` on every run, and the pairing
  code is single-use. A second run is therefore already destructive and already cannot
  re-pair — that is pre-existing, not introduced here. Persisting a completed registration
  belongs with any future resume work for the terminal path, alongside a guard on the
  truncate. **Do not silently make setup "idempotent" in this plan**; it needs its own
  design decision.
- **Terminal → cloud fallback, provisioned.** The ranking logic exists and is exercised by
  Task 4's test, but nothing in a real install provisions two peers today: the wizard writes
  a single `SYNC_PEERS` entry, so a second peer is still something the owner adds by hand.
- **Staff-client path.** `WebVersionUnchangedTest`'s hand-copied reference rows stay as
  they are; superseding the stopgap is a separate, explicitly-tested change.
- **Phase 8 owner-run checklist**, Coolify/Postgres deployment, printer checks, GitHub CI,
  clean-VM installer verification, B1 parity gate, B10/B5 Postgres failures.
- **Desktop auto-update** — separate spec, separate plan, after this one.
