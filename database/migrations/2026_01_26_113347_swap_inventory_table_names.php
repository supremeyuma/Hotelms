<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Rename 'inventory_stocks' to a temp name
        Schema::rename('inventory_stocks', 'temp_inventory_table');
        
        // 2. Rename 'inventory_movements' to 'inventory_stocks'
        Schema::rename('inventory_movements', 'inventory_stocks');
        
        // 3. Rename the temp table to 'inventory_movements'
        Schema::rename('temp_inventory_table', 'inventory_movements');
    }

    public function down(): void
    {
        // Reverse the logic for rollback
        Schema::rename('inventory_movements', 'temp_inventory_table');
        Schema::rename('inventory_stocks', 'inventory_movements');
        Schema::rename('temp_inventory_table', 'inventory_stocks');
    }
};
