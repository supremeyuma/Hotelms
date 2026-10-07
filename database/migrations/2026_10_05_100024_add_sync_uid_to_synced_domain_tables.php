<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Spec 5.4: every synced row retains the ULID minted at its origin node, so
 * replaying a change is a primary-key no-op and application is idempotent.
 *
 * Widening sync_changes.row_id (migration 100022) is not enough on its own.
 * Six of the fifteen synced tables already use a ULID primary key, but the
 * other nine use an auto-increment integer, so a ULID stored in row_id has
 * nothing to point at and every apply would miss. Rather than convert nine
 * primary keys -- which would move every foreign key, report and existing
 * query in a system with 165 applied migrations and live data -- each synced
 * table gets its own sync_uid column and sync_changes.row_id references that.
 * The integer `id` stays the primary key and stays what the rest of the
 * application uses, so nothing outside sync changes.
 *
 * Existing rows are backfilled below. Without the backfill every pre-existing
 * row would be unaddressable by sync and would be re-created as a duplicate on
 * first apply.
 */
return new class extends Migration
{
    private const SYNCED_TABLES = [
        'pos_dockets',
        'pos_docket_items',
        'pos_docket_payments',
        'pos_print_logs',
        'pos_sessions',
        'inventory_movements',
        'inventory_items',
        'inventory_locations',
        'drink_stock_movements',
        'payments',
        'audit_logs',
        'room_types',
        'rooms',
        'staff_profiles',
        'bookings',
    ];

    public function up(): void
    {
        foreach (self::SYNCED_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('sync_uid', 26)->nullable()->unique();
            });
        }

        foreach (self::SYNCED_TABLES as $tableName) {
            $this->backfill($tableName);
        }
    }

    public function down(): void
    {
        foreach (self::SYNCED_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['sync_uid']);
                $table->dropColumn('sync_uid');
            });
        }
    }

    /**
     * Mint one ULID per pre-existing row, in id order, in bounded batches.
     *
     * A single statement cannot mint a distinct ULID per row portably across
     * SQLite, MySQL and PostgreSQL, so this issues one update per row. The
     * tables are still small at this point in the product's life, and sync is
     * disabled by default, so the cost is paid once by an operator who has
     * opted in.
     */
    private function backfill(string $tableName): void
    {
        DB::table($tableName)
            ->whereNull('sync_uid')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($tableName) {
                foreach ($rows as $row) {
                    DB::table($tableName)
                        ->where('id', $row->id)
                        ->update(['sync_uid' => (string) Str::ulid()]);
                }
            });
    }
};
