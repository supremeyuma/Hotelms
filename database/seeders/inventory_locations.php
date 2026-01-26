<?php
// database/migrations/2026_01_22_000004_seed_inventory_locations.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('inventory_locations')->insert([
            ['name' => 'Main Store', 'type' => 'main_store', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Kitchen Store', 'type' => 'kitchen', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Bar Store', 'type' => 'bar', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Laundry Store', 'type' => 'laundry', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('inventory_locations')->truncate();
    }
};
