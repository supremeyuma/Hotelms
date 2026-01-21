<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $settings = [
            ['key' => 'hotel_name', 'value' => 'Lagos Harbor Hotel', 'meta' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'hotel_email', 'value' => 'info@lagosharborhotel.com', 'meta' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'hotel_phone', 'value' => '+2348090000000', 'meta' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'hotel_address', 'value' => '1 Marina Rd, Victoria Island, Lagos', 'meta' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'map_embed_url', 'value' => 'https://www.google.com/maps/embed?pb=!1m18!...', 'meta' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'site_whatsapp', 'value' => '+2348090000000', 'meta' => null, 'created_at' => $now, 'updated_at' => $now],
        ];

        foreach ($settings as $s) {
            DB::table('settings')->updateOrInsert(['key' => $s['key']], $s);
        }
    }
}
