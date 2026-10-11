<?php

namespace App\Sync;

use App\Sync\Policies\AdditivePolicy;
use App\Sync\Policies\ConflictPolicy;
use App\Sync\Policies\LastWriteWinsPolicy;
use App\Sync\Policies\MergePolicy;
use App\Sync\Policies\SyncPolicy;

final class SyncScope
{
    /**
     * Spec section 6. Classification principle: does the row represent
     * something that happened, or something that is currently true?
     *
     * Every table in config/sync_scope.php must have a policy here, and the
     * tripwire test asserts it. Some registered tables do not (yet) carry sync
     * fields, but they can still arrive in a payload and somebody has to have
     * decided their semantics: Class 3 rows are never emitted as authoritative,
     * and Class 5 rows are written by the cloud only.
     *
     * @var array<string, class-string<\App\Sync\Policies\SyncPolicy>>
     */
    private const CATALOGUE = [
        // Class 1 - immutable facts. Append-only, never merged.
        'pos_dockets' => MergePolicy::class,
        'pos_docket_items' => MergePolicy::class,
        'pos_docket_payments' => MergePolicy::class,
        'pos_print_logs' => MergePolicy::class,
        'inventory_movements' => MergePolicy::class,
        'drink_stock_movements' => MergePolicy::class,
        'charges' => MergePolicy::class,
        'audit_logs' => MergePolicy::class,

        // Class 2 - mutable reference data. Row-level last-write-wins.
        'inventory_items' => LastWriteWinsPolicy::class,
        'inventory_locations' => LastWriteWinsPolicy::class,
        'pos_devices' => LastWriteWinsPolicy::class,
        'menu_items' => LastWriteWinsPolicy::class,
        'menu_categories' => LastWriteWinsPolicy::class,
        'menu_subcategories' => LastWriteWinsPolicy::class,
        'menu_inventory_recipes' => LastWriteWinsPolicy::class,
        'room_types' => LastWriteWinsPolicy::class,
        'rooms' => LastWriteWinsPolicy::class,
        'staff_profiles' => LastWriteWinsPolicy::class,
        'users' => LastWriteWinsPolicy::class,

        // Class 3 - derived balances. Never authoritative; recomputed.
        'inventory_stocks' => AdditivePolicy::class,
        'drink_stock' => AdditivePolicy::class,

        // Class 4 - hard conflicts. Blocked, surfaced to a human.
        'bookings' => ConflictPolicy::class,
        'booking_rooms' => ConflictPolicy::class,
        'pos_sessions' => ConflictPolicy::class,
        'payments' => ConflictPolicy::class,

        // Class 5 - cloud-authoritative. Pushed down, never contested.
        'settings' => MergePolicy::class,
        'roles' => MergePolicy::class,
    ];

    /**
     * @throws \InvalidArgumentException when no policy was decided for the key
     */
    public static function policyFor(string $entityKey): SyncPolicy
    {
        $class = self::CATALOGUE[$entityKey] ?? null;

        if ($class === null) {
            throw new \InvalidArgumentException("No sync policy registered for [{$entityKey}].");
        }

        return new $class($entityKey);
    }

    public static function has(string $entityKey): bool
    {
        return isset(self::CATALOGUE[$entityKey]);
    }

    /**
     * May this node emit a change for this table?
     *
     * Some tables are reference data that only the author node writes. A
     * terminal that emits a Class 2 change for one of them would fight the
     * cloud over live configuration, and last-write-wins means it can win with
     * a stale clock and leave no conflict record.
     *
     * Two things gate this. `config('sync.node_type')` defaults to 'terminal',
     * so a node that never configured itself is treated as the most restricted
     * kind rather than the least. `config('sync_scope.read_only')` carries the
     * verified list from config/sync_scope.php, which the tripwire test keeps
     * aligned with the manifest.
     */
    public static function mayEmit(string $entityKey): bool
    {
        if (config('sync.node_type') !== 'terminal') {
            return true;
        }

        $readOnly = (array) config('sync_scope.read_only', []);

        return ! in_array($entityKey, $readOnly, true);
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::CATALOGUE);
    }
}
