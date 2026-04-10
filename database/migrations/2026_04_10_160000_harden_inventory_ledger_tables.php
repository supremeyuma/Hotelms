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

            if (Schema::hasColumn('inventory_stocks', 'type')) {
                $table->string('type')->nullable()->default('adjustment')->change();
            }
        });

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

            if (Schema::hasColumn('inventory_movements', 'type')) {
                $table->enum('type', ['in', 'out', 'transfer_in', 'transfer_out', 'adjustment'])->change();
            }

            $table->decimal('quantity', 12, 2)->default(0)->change();
        });

        $stockIndexes = $driver === 'mysql'
            ? collect(DB::select('SHOW INDEX FROM inventory_stocks'))->pluck('Key_name')->all()
            : [];

        if (! in_array('inventory_stocks_item_location_unique', $stockIndexes, true)) {
            Schema::table('inventory_stocks', function (Blueprint $table) {
                $table->unique(
                    ['inventory_item_id', 'inventory_location_id'],
                    'inventory_stocks_item_location_unique'
                );
            });
        }

        $movementIndexes = $driver === 'mysql'
            ? collect(DB::select('SHOW INDEX FROM inventory_movements'))->pluck('Key_name')->all()
            : [];

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
};
