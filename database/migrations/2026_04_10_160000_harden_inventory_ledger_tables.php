<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->decimal('quantity', 12, 2)->default(0)->change();
            $table->decimal('low_stock_threshold', 12, 2)->default(10)->change();
        });

        Schema::table('inventory_stocks', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_stocks', 'inventory_item_id')) {
                $table->foreignId('inventory_item_id')
                    ->after('id')
                    ->constrained('inventory_items')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('inventory_stocks', 'inventory_location_id')) {
                $table->foreignId('inventory_location_id')
                    ->after('inventory_item_id')
                    ->constrained('inventory_locations')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('inventory_stocks', 'quantity')) {
                $table->decimal('quantity', 12, 2)->default(0)->after('inventory_location_id');
            } else {
                $table->decimal('quantity', 12, 2)->default(0)->change();
            }
        });

        // `type` is an enum column. Laravel cannot emit a valid enum change for
        // Postgres, so it is handled explicitly per driver below.
        if (Schema::hasColumn('inventory_stocks', 'type')) {
            $this->changeTypeColumn('inventory_stocks', [], true, 'adjustment', $driver);
        }

        Schema::table('inventory_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_movements', 'staff_id')) {
                $table->foreignId('staff_id')
                    ->nullable()
                    ->after('inventory_location_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('inventory_movements', 'reason')) {
                $table->string('reason')->nullable()->after('quantity');
            }

            if (! Schema::hasColumn('inventory_movements', 'reference_type')) {
                $table->string('reference_type')->nullable()->after('reason');
            }

            if (! Schema::hasColumn('inventory_movements', 'reference_id')) {
                $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
            }

            if (! Schema::hasColumn('inventory_movements', 'meta')) {
                $table->json('meta')->nullable()->after('reference_id');
            }

            $table->decimal('quantity', 12, 2)->default(0)->change();
        });

        if (Schema::hasColumn('inventory_movements', 'type')) {
            $this->changeTypeColumn(
                'inventory_movements',
                ['in', 'out', 'transfer_in', 'transfer_out', 'adjustment'],
                false,
                null,
                $driver
            );
        }

        $stockIndexes = [];
        if ($driver === 'mysql') {
            $stockIndexes = collect(DB::select('SHOW INDEX FROM inventory_stocks'))->pluck('Key_name')->all();
        } elseif ($driver === 'pgsql') {
            $stockIndexes = collect(DB::select("SELECT indexname AS Key_name FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'inventory_stocks'"))->pluck('Key_name')->all();
        }

        if (! in_array('inventory_stocks_item_location_unique', $stockIndexes, true)) {
            Schema::table('inventory_stocks', function (Blueprint $table) {
                $table->unique(
                    ['inventory_item_id', 'inventory_location_id'],
                    'inventory_stocks_item_location_unique'
                );
            });
        }

        $movementIndexes = [];
        if ($driver === 'mysql') {
            $movementIndexes = collect(DB::select('SHOW INDEX FROM inventory_movements'))->pluck('Key_name')->all();
        } elseif ($driver === 'pgsql') {
            $movementIndexes = collect(DB::select("SELECT indexname AS Key_name FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'inventory_movements'"))->pluck('Key_name')->all();
        }

        Schema::table('inventory_movements', function (Blueprint $table) use ($movementIndexes) {
            if (! in_array('inventory_movements_created_at_index', $movementIndexes, true)) {
                $table->index('created_at');
            }

            if (! in_array('inventory_movements_reference_type_reference_id_index', $movementIndexes, true)) {
                $table->index(['reference_type', 'reference_id']);
            }
        });
    }

    public function down(): void
    {
    }

    /**
     * Change the enum-backed `type` column without relying on Laravel's
     * broken enum change support for Postgres.
     *
     * @param  array<int, string>  $allowed  Allowed values; empty = plain string column.
     */
    private function changeTypeColumn(string $table, array $allowed, bool $nullable, ?string $default, string $driver): void
    {
        if ($driver === 'pgsql') {
            $this->dropTypeCheckConstraints($table);

            DB::statement(sprintf('ALTER TABLE "%s" ALTER COLUMN "type" TYPE varchar(255)', $table));
            DB::statement(sprintf('ALTER TABLE "%s" ALTER COLUMN "type" %s NOT NULL', $table, $nullable ? 'DROP' : 'SET'));

            if ($default === null) {
                DB::statement(sprintf('ALTER TABLE "%s" ALTER COLUMN "type" DROP DEFAULT', $table));
            } else {
                DB::statement(sprintf("ALTER TABLE \"%s\" ALTER COLUMN \"type\" SET DEFAULT '%s'", $table, $default));
            }

            if ($allowed !== []) {
                $values = "'".implode("', '", $allowed)."'";
                DB::statement(sprintf(
                    'ALTER TABLE "%s" ADD CONSTRAINT "%s_type_check" CHECK ("type" in (%s))',
                    $table,
                    $table,
                    $values
                ));
            }

            return;
        }

        if ($driver === 'mysql') {
            $definition = $allowed === []
                ? sprintf('VARCHAR(255) %s', $nullable ? 'NULL' : 'NOT NULL')
                : sprintf("ENUM(%s) %s", "'".implode("', '", $allowed)."'", $nullable ? 'NULL' : 'NOT NULL');

            if ($default !== null) {
                $definition .= sprintf(" DEFAULT '%s'", $default);
            }

            DB::statement(sprintf('ALTER TABLE `%s` MODIFY COLUMN `type` %s', $table, $definition));

            return;
        }

        // sqlite and other drivers: rebuild the column via the schema builder.
        Schema::table($table, function (Blueprint $blueprint) use ($allowed, $nullable, $default) {
            $column = $allowed === []
                ? $blueprint->string('type')
                : $blueprint->enum('type', $allowed);

            if ($nullable) {
                $column->nullable();
            }

            if ($default !== null) {
                $column->default($default);
            }

            $column->change();
        });
    }

    /**
     * Drop the CHECK constraints backing an enum `type` column on Postgres.
     *
     * Constraint names are not updated when a table is renamed, so the
     * constraint is located by table + column rather than by its name.
     */
    private function dropTypeCheckConstraints(string $table): void
    {
        // Postgres may deparse the identifier as `(type)` or `("type")`, so the
        // column is matched as a standalone word rather than a quoted literal.
        $sql = sprintf(<<<'SQL'
            SELECT con.conname AS name
            FROM pg_constraint con
            JOIN pg_class rel ON rel.oid = con.conrelid
            JOIN pg_namespace ns ON ns.oid = rel.relnamespace
            WHERE con.contype = 'c'
              AND rel.relname = '%s'
              AND ns.nspname = current_schema()
              AND pg_get_constraintdef(con.oid) ~ '(^|[^a-zA-Z0-9_])type([^a-zA-Z0-9_]|$)'
            SQL, $table);

        $constraints = DB::select($sql);

        foreach ($constraints as $constraint) {
            DB::statement(sprintf('ALTER TABLE "%s" DROP CONSTRAINT "%s"', $table, $constraint->name));
        }
    }
};
