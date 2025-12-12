<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seed orders and order_items across departments (kitchen, laundry, housekeeping, maintenance)
 */
class OrdersSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $bookings = DB::table('bookings')->pluck('id')->toArray();
        $rooms = DB::table('rooms')->pluck('id')->toArray();
        $users = DB::table('users')->whereIn('role_id', function($q){
            $q->select('id')->from('roles')->where('slug','guest');
        })->pluck('id')->toArray();

        if (empty($rooms) || empty($users)) return;

        $departments = ['kitchen','laundry','housekeeping','maintenance'];
        $orders = [];
        $items = [];

        for ($i = 0; $i < 20; $i++) {
            $roomId = $rooms[$i % count($rooms)];
            $userId = $users[$i % count($users)];
            $dept = $departments[$i % count($departments)];
            $orderCode = strtoupper('ORD' . Str::random(6));
            $orders[] = [
                'booking_id' => $bookings[$i % max(1,count($bookings))] ?? null,
                'user_id' => $userId,
                'order_code' => $orderCode,
                'total' => 0,
                'status' => ($i % 6 === 0) ? 'ready' : (($i % 7 === 0) ? 'delivered' : 'pending'),
                'created_at' => $now->copy()->subHours(rand(1,72)),
                'updated_at' => $now,
            ];
        }

        $orderIds = [];
        foreach ($orders as $o) {
            $orderIds[] = DB::table('orders')->insertGetId($o);
        }

        // Add items
        $sampleMenu = [
            ['name' => 'Club Sandwich', 'price' => 2500],
            ['name' => 'Grilled Salmon', 'price' => 6500],
            ['name' => 'Laundry - Small', 'price' => 1500],
            ['name' => 'Extra Towels', 'price' => 500],
            ['name' => 'Housekeeping - Deep Clean', 'price' => 8000],
        ];

        foreach ($orderIds as $idx => $oid) {
            $countItems = rand(1,3);
            $total = 0;
            for ($j = 0; $j < $countItems; $j++) {
                $menu = $sampleMenu[array_rand($sampleMenu)];
                $qty = rand(1,2);
                $sub = $menu['price'] * $qty;
                DB::table('order_items')->insert([
                    'order_id' => $oid,
                    'item_name' => $menu['name'],
                    'qty' => $qty,
                    'price' => $menu['price'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $total += $sub;
            }
            DB::table('orders')->where('id', $oid)->update(['total' => $total]);
        }
    }
}
