<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PropertiesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('properties')->insert([
            [
                'name' => 'Lagos Harbor Hotel',
                'location' => 'Victoria Island, Lagos, Nigeria',
                'amenities' => json_encode(['wifi','pool','spa','restaurant','gym']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
