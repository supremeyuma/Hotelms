<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Creates order events for seeded orders
 */
class OrderEventsSeeder extends Seeder
{
    public function run(): void
    {
        $orders = DB::table('orders')->take(15)->get();
        foreach ($orders as $o) {
            DB::table('order_events')->insert([
                'order_id' => $o->id,
                'staff_id' => null,
                'event' => 'created',
                'meta' => json_encode(['note' => 'Seeded order']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($o->status === 'ready' || $o->status === 'delivered') {
                DB::table('order_events')->insert([
                    'order_id' => $o->id,
                    'staff_id' => null,
                    'event' => 'status_changed',
                    'meta' => json_encode(['to' => $o->status]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
