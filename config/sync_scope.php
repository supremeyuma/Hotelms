<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Terminal Synchronization Scope
    |--------------------------------------------------------------------------
    |
    | The transitive closure of what Club POS and Inventory actually read and
    | write. A terminal does not synchronize the whole hotel database.
    |
    | This is the verified closure, measured by walking the real model graph
    | (see docs/SYNC_SCOPE.md and SyncScopeResolver), not a guess. The tripwire
    | test fails when application code touches a table that is neither listed
    | here nor recorded in `excluded`, so adding a dependency is a deliberate
    | act rather than a silent one.
    |
    | Policy labels map to App\Sync\Policies classes in SyncScope::CATALOGUE:
    |   merge     -> MergePolicy       (Class 1, immutable facts)
    |   lww       -> LastWriteWinsPolicy (Class 2, mutable reference data)
    |   additive  -> AdditivePolicy    (Class 3, derived balances)
    |   conflict  -> ConflictPolicy    (Class 4, hard conflicts, human-resolved)
    |   push_down -> MergePolicy       (Class 5, cloud-authoritative config)
    |
    */

    'tables' => [
        // Class 1 - immutable facts, appended and never contested.
        'pos_dockets' => 'merge',
        'pos_docket_items' => 'merge',
        'pos_docket_payments' => 'merge',
        'pos_print_logs' => 'merge',
        'inventory_movements' => 'merge',
        'drink_stock_movements' => 'merge',
        'charges' => 'merge',

        // Class 2 - mutable reference data, row-level last-write-wins.
        'inventory_items' => 'lww',
        'inventory_locations' => 'lww',
        'pos_devices' => 'lww',
        'menu_items' => 'lww',
        'menu_categories' => 'lww',
        'menu_subcategories' => 'lww',
        'menu_inventory_recipes' => 'lww',
        'rooms' => 'lww',
        'room_types' => 'lww',
        'staff_profiles' => 'lww',
        'users' => 'lww',

        // Class 3 - derived balances. Never authoritative; recomputed locally.
        'inventory_stocks' => 'additive',
        'drink_stock' => 'additive',

        // Class 4 - hard conflicts. Blocked and surfaced to a human.
        'bookings' => 'conflict',
        'booking_rooms' => 'conflict',
        'pos_sessions' => 'conflict',

        // Class 5 - cloud-authoritative. Pushed down, never contested.
        'settings' => 'push_down',
        'roles' => 'push_down',
    ],

    /*
    |--------------------------------------------------------------------------
    | Always Synchronized
    |--------------------------------------------------------------------------
    |
    | Tables that are unambiguously in scope but are not reachable by following
    | Eloquent relationships from the POS/Inventory entry points. A drink-stock
    | balance or a receipt log hangs off its own ledger rather than off a
    | docket; a role assignment is referenced by id; a POS device is resolved by
    | code. Enumerating them here keeps the inverse tripwire honest: a manifest
    | table that the resolver cannot reach is either recorded here with a
    | reason or removed as dead weight, never left as a false failure.
    |
    */

    'always_sync' => [
        'pos_print_logs' => 'Receipt history; addressed by docket id, not a relation from it.',
        'drink_stock' => 'Bar balance; the drink-stock ledger hangs off a menu item, not a docket.',
        'drink_stock_movements' => 'Bar ledger; same direction as drink_stock.',
        'menu_inventory_recipes' => 'Recipe-to-stock mapping; referenced by menu item id.',
        'staff_profiles' => 'POS PIN and shift actor; reached by id, not a docket relation.',
        'roles' => 'Authorization; shared staff accounts, resolved by user id.',
        'settings' => 'Cloud-authoritative configuration, including the booking cutoff.',
        'booking_rooms' => 'Room assignment join; the folio `charges.room_id` comes from it.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Declared But Without A Capture Path
    |--------------------------------------------------------------------------
    |
    | Tables this manifest declares in scope that have no capture path today:
    | they carry no sync fields and have no model in
    | App\Sync\SyncEntityRegistry, so no change for them can be produced or
    | applied. They are recorded here, each with a reason, rather than silently
    | omitted; SyncScopeManifestTest fails when a manifest table is neither
    | registered nor listed here. Implementing any of them is new engine scope,
    | not a gap this list papers over.
    |
    | Reasons reuse the `always_sync` wording where a table already appears
    | there; the rest state why the table is unsynced today.
    |
    */

    'no_capture_path' => [
        // Class 1 fact with no model and no sync fields.
        'charges' => 'Folio charges do not travel; a charge is written by the node that owns the folio.',

        // Class 2 reference data authored centrally; read-only on a terminal.
        'pos_devices' => 'POS device registry is authored centrally and resolved by code; no sync fields, no capture path.',
        'menu_items' => 'Menu catalogue is authored centrally and read-only on a terminal; no sync fields, no capture path.',
        'menu_categories' => 'Menu catalogue is authored centrally and read-only on a terminal; no sync fields, no capture path.',
        'menu_subcategories' => 'Menu catalogue is authored centrally and read-only on a terminal; no sync fields, no capture path.',
        'menu_inventory_recipes' => 'Recipe-to-stock mapping; referenced by menu item id.',
        'users' => 'Staff accounts are authored centrally; a terminal authenticates against its local copy; no sync fields, no capture path.',

        // Class 3 derived balances; never authoritative.
        'inventory_stocks' => 'Derived stock balance; recomputed locally from inventory_movements and never captured.',
        'drink_stock' => 'Bar balance; the drink-stock ledger hangs off a menu item, not a docket.',

        // Class 4 pivot the terminal cannot apply without its join rows.
        'booking_rooms' => 'A cloud-authored multi-room booking reaches a terminal with only the legacy bookings.room_id scalar.',

        // Class 5 configuration authored on the cloud.
        'settings' => 'Cloud-authoritative configuration, including the booking cutoff.',
        'roles' => 'Authorization; shared staff accounts, resolved by user id.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Reference Snapshot
    |--------------------------------------------------------------------------
    |
    | These tables are carried by GET /api/sync/reference when a newly
    | installed terminal pulls its initial reference data. Sync cannot move
    | any of them: none has a capture path in SyncEntityRegistry.
    |
    | The list is deliberately not derived from `no_capture_path`, which also
    | holds transactional and derived tables (charges, booking_rooms,
    | inventory_stocks, drink_stock) that must never be snapshotted. It is
    | curated, and each entry records why it travels.
    |
    | Declared in dependency order because the pull writes into a database
    | where foreign keys are enforced (DB_FOREIGN_KEYS defaults to true on
    | sqlite), so each insert must find its parent already written.
    |
    | ReferenceSnapshotScopeTest proves this set is disjoint from
    | SyncEntityRegistry — two transports must never race over one primary
    | key — and that every foreign-key parent is available before its child
    | is written.
    |
    */

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

    /*
    |--------------------------------------------------------------------------
    | Node Types
    |--------------------------------------------------------------------------
    |
    | Which node kinds sync this scope. A staff client holds no database
    | (spec section 4.1), so it appears nowhere.
    |
    */

    'nodes' => ['cloud', 'lan_server', 'terminal'],

    /*
    |--------------------------------------------------------------------------
    | Read-Only Tables On A Terminal
    |--------------------------------------------------------------------------
    |
    | A terminal reads these for labels, prices and permissions. It must never
    | emit a sync change for them.
    |
    | This is not a style preference. Without this list a terminal that saves a
    | menu item - a stray edit, a failed import replay, an over-eager admin
    | action - emits a Class 2 change and fights the cloud over a menu price.
    | Last-write-wins means whoever wrote last wins, so an offline terminal with
    | a stale clock can overwrite live cloud configuration and nothing will
    | report a conflict.
    |
    | The cloud and the LAN server have no such restriction; only `terminal`
    | nodes are gated. Reference data is authored centrally and pushed down.
    |
    */

    'read_only' => [
        'menu_items',
        'menu_categories',
        'menu_subcategories',
        'menu_inventory_recipes',
        'room_types',
        'rooms',
        'inventory_locations',
        'staff_profiles',
        'users',
        'roles',
        'settings',
    ],

    /*
    |--------------------------------------------------------------------------
    | Explicitly Excluded
    |--------------------------------------------------------------------------
    |
    | Reached by walking the model graph but deliberately outside the terminal
    | scope. Recorded with a reason so a reader does not assume an oversight.
    | Derived reporting projections are excluded because importing them would
    | import projection drift (spec section 6.1).
    |
    */

    'excluded' => [
        // Reached through Booking/Room relationships but not needed to sell.
        'orders' => 'Guest/room-service ordering is out of scope offline (spec section 15).',
        'order_items' => 'Part of guest ordering; out of scope offline.',
        'order_events' => 'Part of guest ordering; out of scope offline.',
        'payments' => 'Folio posting writes `charges`; capture/refund is a cloud/LAN Class 4 concern, not an offline terminal path.',
        'discount_codes' => 'Promotions authored centrally; not a POS critical path.',
        'discount_code_redemptions' => 'Promotions authored centrally; not a POS critical path.',
        'images' => 'Static media cached at install.',
        'menu_item_images' => 'Static media cached at install.',
        'properties' => 'Single-property deployment; a node binds one property at setup (spec section 4.4), so sync never moves it — but it is served by the reference snapshot because synced rooms and room types reference it.',
        'room_access_tokens' => 'Door access, not POS or Inventory.',
        'room_cleanings' => 'Housekeeping, not POS or Inventory.',
        'room_payments' => 'Room booking payments, not POS or Inventory.',

        // Recorded so a reader knows these are deliberate, reached or not.
        'journal_entries' => 'Manager and accounting surface (spec section 7 corrects the folio wording to `charges`).',
        'journal_lines' => 'Manager and accounting surface.',
        'accounting_periods' => 'Manager and accounting surface.',
        'audit_logs' => 'Local audit trail; each node keeps its own (Class 1 append, not exchanged for POS correctness).',
        'events' => 'Not on a POS critical path.',
        'event_tickets' => 'Not on a POS critical path.',
        'galleries' => 'Static reference data cached at install.',
        'jobs' => 'Local infrastructure.',
        'failed_jobs' => 'Local infrastructure.',

        // Derived reporting projections (spec section 6.1).
        'reporting_pos_facts' => 'Derived projection; each node rebuilds from Class 1 source rows.',
        'reporting_booking_facts' => 'Derived projection; rebuilt locally.',
        'reporting_room_daily_facts' => 'Derived projection; rebuilt locally.',
        'reporting_order_facts' => 'Derived projection; rebuilt locally.',
        'reporting_charge_facts' => 'Derived projection; rebuilt locally.',
        'reporting_payment_facts' => 'Derived projection; rebuilt locally.',
        'reporting_staff_daily_facts' => 'Derived projection; rebuilt locally.',
        'reporting_department_daily_facts' => 'Derived projection; rebuilt locally.',
        'reporting_maintenance_facts' => 'Derived projection; rebuilt locally.',
        'reporting_laundry_facts' => 'Derived projection; rebuilt locally.',
        'reporting_events' => 'Derived projection; rebuilt locally.',
        'reporting_exceptions' => 'Derived projection; rebuilt locally.',
        'reporting_metric_definitions' => 'Derived projection definition; rebuilt locally.',
    ],
];
