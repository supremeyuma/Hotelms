<?php
// database/seeders/GallerySeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Gallery;

class GallerySeeder extends Seeder
{
    public function run(): void
    {
        $images = [
            ['category' => 'beach', 'image_path' => 'public/gallery/beach-1.jpg'],
            ['category' => 'beach', 'image_path' => 'public/gallery/beach-2.jpg'],
            ['category' => 'resort', 'image_path' => 'public/gallery/resort-1.jpg'],
            ['category' => 'resort', 'image_path' => 'public/gallery/resort-2.jpg'],
            ['category' => 'club', 'image_path' => 'public/gallery/club-1.jpg'],
            ['category' => 'club', 'image_path' => 'public/gallery/club-2.jpg'],
            ['category' => 'lounge', 'image_path' => 'public/gallery/lounge-1.jpg'],
            ['category' => 'lounge', 'image_path' => 'public/gallery/lounge-2.jpg'],
        ];

        foreach ($images as $index => $img) {
            Gallery::create([
                'category' => $img['category'],
                'image_path' => $img['image_path'],
                'order' => $index,
                'is_active' => true
            ]);
        }
    }
}
