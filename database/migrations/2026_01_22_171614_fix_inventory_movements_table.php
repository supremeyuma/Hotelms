<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_movements', 'inventory_item_id')) {
                $table->foreignId('inventory_item_id')
                    ->after('id')
                    ->constrained('inventory_items')
                    ->cascadeOnDelete();
            }

            if (!Schema::hasColumn('inventory_movements', 'inventory_location_id')) {
                $table->foreignId('inventory_location_id')
                    ->after('inventory_item_id')
                    ->constrained('inventory_locations')
                    ->cascadeOnDelete();
            }

            if (!Schema::hasColumn('inventory_movements', 'type')) {
                $table->enum('type', ['in', 'out', 'transfer', 'adjustment'])
                    ->after('inventory_location_id');
            }

            if (!Schema::hasColumn('inventory_movements', 'quantity')) {
                $table->unsignedInteger('quantity')->after('type');
            }
        });
    }

    public function down(): void
    {
        // no rollback — data safety
    }
};
