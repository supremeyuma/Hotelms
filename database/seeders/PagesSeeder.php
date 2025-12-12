<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Basic CMS pages for About, Terms, Privacy, Contact
 * If the app uses a pages table elsewhere, adapt accordingly.
 * Here we'll use settings keys for page content (simpler).
 */
class PagesSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            ['key' => 'page_about', 'value' => 'Lagos Harbor Hotel is a luxury hotel in Victoria Island offering comfortable stay, dining and events.','meta' => null],
            ['key' => 'page_terms', 'value' => 'Terms and conditions apply.','meta' => null],
            ['key' => 'page_privacy', 'value' => 'We value your privacy.','meta' => null],
            ['key' => 'page_contact_info', 'value' => 'Reach us at +2348090000000 or info@lagosharborhotel.com','meta' => null],
        ];

        foreach ($pages as $p) {
            DB::table('settings')->updateOrInsert(['key' => $p['key']], ['value' => $p['value'], 'meta' => $p['meta'], 'updated_at' => now(), 'created_at' => now()]);
        }
    }
}
