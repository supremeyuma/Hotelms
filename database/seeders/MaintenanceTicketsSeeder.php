<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed a handful of maintenance tickets
 */
class MaintenanceTicketsSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = DB::table('rooms')->pluck('id')->toArray();
        $staff = DB::table('users')->where('email','like','%maintenance%')->pluck('id')->toArray();
        $now = now();

        $tickets = [
            ['room_id' => $rooms[0] ?? null, 'staff_id' => $staff[0] ?? null, 'title' => 'Aircon not cooling', 'description' => 'AC making noise and not cooling efficiently', 'status' => 'open', 'meta' => null, 'created_at' => $now, 'updated_at' => $now],
            ['room_id' => $rooms[1] ?? null, 'staff_id' => $staff[0] ?? null, 'title' => 'Leaking sink', 'description' => 'Bathroom sink leaking', 'status' => 'in_progress', 'meta' => null, 'created_at' => $now->copy()->subDays(2), 'updated_at' => $now],
            ['room_id' => $rooms[2] ?? null, 'staff_id' => null, 'title' => 'Broken light', 'description' => 'Ceiling light flickers', 'status' => 'open', 'meta' => null, 'created_at' => $now->copy()->subDays(5), 'updated_at' => $now],
            ['room_id' => $rooms[3] ?? null, 'staff_id' => $staff[0] ?? null, 'title' => 'Door handle loose', 'description' => 'Room door handle loose', 'status' => 'resolved', 'meta' => null, 'created_at' => $now->copy()->subDays(8), 'updated_at' => $now],
            ['room_id' => $rooms[4] ?? null, 'staff_id' => null, 'title' => 'TV not turning on', 'description' => 'Guest reported TV power issue', 'status' => 'open', 'meta' => null, 'created_at' => $now->copy()->subDays(1), 'updated_at' => $now],
            ['room_id' => $rooms[5] ?? null, 'staff_id' => null, 'title' => 'Water heater failure', 'description' => 'No hot water in shower', 'status' => 'open', 'meta' => null, 'created_at' => $now->copy()->subDays(3), 'updated_at' => $now],
        ];

        DB::table('maintenance_tickets')->insert($tickets);
    }
}
