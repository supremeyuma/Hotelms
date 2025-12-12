<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoomTypesSeeder extends Seeder
{
    public function run(): void
    {
        $property = DB::table('properties')->first();
        if (! $property) return;

        $now = now();

        $types = [
            ['property_id' => $property->id, 'title' => 'Standard Room', 'max_occupancy' => 2, 'base_price' => 35000.00, 'features' => json_encode(['wifi','ensuite','tv']), 'created_at' => $now, 'updated_at' => $now],
            ['property_id' => $property->id, 'title' => 'Deluxe Room', 'max_occupancy' => 3, 'base_price' => 55000.00, 'features' => json_encode(['wifi','ensuite','tv','minibar']), 'created_at' => $now, 'updated_at' => $now],
            ['property_id' => $property->id, 'title' => 'Executive Suite', 'max_occupancy' => 4, 'base_price' => 120000.00, 'features' => json_encode(['living_area','wifi','ensuite','minibar','balcony']), 'created_at' => $now, 'updated_at' => $now],
            ['property_id' => $property->id, 'title' => 'Family Suite', 'max_occupancy' => 5, 'base_price' => 150000.00, 'features' => json_encode(['2_bedrooms','wifi','kitchenette']), 'created_at' => $now, 'updated_at' => $now],
            ['property_id' => $property->id, 'title' => 'Presidential Suite', 'max_occupancy' => 6, 'base_price' => 350000.00, 'features' => json_encode(['private_pool','living_area','butler_service']), 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('room_types')->insert($types);
    }
}
