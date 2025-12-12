<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoomsSeeder extends Seeder
{
    public function run(): void
    {
        $property = DB::table('properties')->first();
        $roomTypes = DB::table('room_types')->where('property_id', $property->id)->get();

        $rooms = [];
        $now = now();

        // create ~24 rooms, distributed across types
        $numbers = range(101, 124);
        $i = 0;
        foreach ($numbers as $n) {
            $type = $roomTypes[$i % $roomTypes->count()];
            $rooms[] = [
                'property_id' => $property->id,
                'room_type_id' => $type->id,
                'room_number' => (string)$n,
                'status' => 'available',
                'meta' => json_encode(['floor' => intval(floor(($n - 100) / 10) + 1)]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $i++;
        }

        DB::table('rooms')->insert($rooms);
    }
}
