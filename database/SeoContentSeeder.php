<?php
// database/seeders/SeoContentSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Content;

class SeoContentSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            'seo.home.title' => 'Luxury Beach Resort',
            'seo.home.description' => 'Private beachfront resort with club and lounge.',
            'seo.home.keywords' => 'beach resort, luxury hotel, club, lounge',
        ];

        foreach ($items as $key => $value) {
            Content::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => 'text']
            );
        }
    }
}
