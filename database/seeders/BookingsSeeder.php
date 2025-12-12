<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Create 12+ bookings (past and future), link to rooms and guests
 */
class BookingsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $property = DB::table('properties')->first();
        $rooms = DB::table('rooms')->pluck('id')->toArray();
        $guests = DB::table('users')->whereIn('role_id', function($q){
            $q->select('id')->from('roles')->where('slug','guest');
        })->pluck('id')->toArray();

        if (! $property || empty($rooms) || empty($guests)) return;

        $bookings = [];

        // Create 12 bookings: mix of past, current, future
        for ($i = 0; $i < 12; $i++) {
            $roomId = $rooms[$i % count($rooms)];
            $userId = $guests[$i % count($guests)];
            $checkIn = now()->subDays(10 - $i)->toDateString();
            $checkOut = now()->subDays(9 - $i)->toDateString();
            if ($i > 7) {
                $checkIn = now()->addDays($i - 7)->toDateString();
                $checkOut = now()->addDays($i - 6)->toDateString();
            }
            $bookings[] = [
                'property_id' => $property->id,
                'room_id' => $roomId,
                'user_id' => $userId,
                'booking_code' => strtoupper('BKG'.Str::random(6)),
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guests' => rand(1,4),
                'total_amount' => rand(30000,150000),
                'status' => ($i % 4 === 0) ? 'cancelled' : (($i % 5 === 0) ? 'checked_in' : 'confirmed'),
                'details' => json_encode(['notes' => 'Demo booking #' . ($i+1)]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('bookings')->insert($bookings);
    }
}
