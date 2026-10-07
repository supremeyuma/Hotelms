<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 5.4: "Every row retains the ULID minted at its origin node, so replaying
 * a change is a primary-key no-op and application is idempotent."
 *
 * The existing unsignedBigInteger row_id cannot hold a 26-character ULID.
 * Widening it here is necessary but NOT sufficient on its own -- 9 of the 15
 * synced domain tables have an integer primary key that a ULID cannot address.
 * The additive half of the fix is migration 100024, which gives every synced
 * domain table its own sync_uid column and points row_id at that instead of at
 * `id`. Domain primary keys stay integers, so no existing query, foreign key
 * or report changes.
 *
 * On SQLite a column change requires a table rebuild rather than an in-place
 * ALTER. Laravel performs that rebuild, and a rebuild can drop indexes that an
 * earlier migration created, so every index these two tables must keep is
 * re-asserted below rather than assumed. Both tables hold no production data
 * yet, so rebuilding is safe.
 */
return new class extends Migration
{
    private const TABLES = ['sync_changes', 'sync_conflicts'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('row_id')->change();
            });
        }

        $this->ensureIndex('sync_changes', ['table_name', 'row_id']);
        $this->ensureIndex('sync_changes', ['hlc']);
        $this->ensureIndex('sync_conflicts', ['table_name', 'row_id']);

        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->index(['row_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['row_id']);
            });

            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('row_id')->change();
            });
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function ensureIndex(string $tableName, array $columns): void
    {
        if (Schema::hasIndex($tableName, $columns)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns) {
            $table->index($columns);
        });
    }
};
