<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed a few inventory logs to correlate with items
 */
class InventoryLogsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $item = DB::table('inventory_items')->first();
        if (! $item) return;

        $logs = [
            ['inventory_item_id' => $item->id, 'staff_id' => null, 'change' => 200, 'type' => 'addition', 'meta' => json_encode(['source'=>'initial_seed']), 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('inventory_logs')->insert($logs);
    }
}
