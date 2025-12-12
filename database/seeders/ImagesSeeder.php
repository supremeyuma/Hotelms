<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds sample images for rooms, lobby, spa, restaurant, exterior, etc.
 * Paths assume storage/app/public/images/... already present for demo assets.
 */
class ImagesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $property = DB::table('properties')->first();

        // pick some rooms
        $rooms = DB::table('rooms')->limit(10)->pluck('id')->toArray();

        $images = [];

        // Lobby, exterior, pool, restaurant, spa images attached to property (imageable_type => 'App\Models\Property')
        $images[] = ['imageable_type' => 'App\Models\Property', 'imageable_id' => $property->id, 'path' => 'images/property/lobby.jpg', 'alt' => 'Lobby', 'meta' => json_encode(['w'=>1600,'h'=>900]), 'created_at' => $now, 'updated_at' => $now];
        $images[] = ['imageable_type' => 'App\Models\Property', 'imageable_id' => $property->id, 'path' => 'images/property/pool.jpg', 'alt' => 'Pool', 'meta' => json_encode(['w'=>1600,'h'=>900]), 'created_at' => $now, 'updated_at' => $now];
        $images[] = ['imageable_type' => 'App\Models\Property', 'imageable_id' => $property->id, 'path' => 'images/property/restaurant.jpg', 'alt' => 'Restaurant', 'meta' => json_encode(['w'=>1600,'h'=>900]), 'created_at' => $now, 'updated_at' => $now];

        // Room images
        $i = 1;
        foreach ($rooms as $rid) {
            $images[] = ['imageable_type' => 'App\Models\Room', 'imageable_id' => $rid, 'path' => "images/rooms/room_{$rid}.jpg", 'alt' => "Room {$rid} photo", 'meta' => json_encode(['order'=>$i]), 'created_at' => $now, 'updated_at' => $now];
            if ($i % 3 === 0) {
                // extra image for some rooms
                $images[] = ['imageable_type' => 'App\Models\Room', 'imageable_id' => $rid, 'path' => "images/rooms/room_{$rid}_2.jpg", 'alt' => "Room {$rid} second photo", 'meta' => json_encode(['order'=>$i+1]), 'created_at' => $now, 'updated_at' => $now];
            }
            $i++;
        }

        // Spa and Gym
        $images[] = ['imageable_type' => 'App\Models\Property', 'imageable_id' => $property->id, 'path' => 'images/property/spa.jpg', 'alt' => 'Spa', 'meta' => json_encode([]), 'created_at' => $now, 'updated_at' => $now];
        $images[] = ['imageable_type' => 'App\Models\Property', 'imageable_id' => $property->id, 'path' => 'images/property/gym.jpg', 'alt' => 'Gym', 'meta' => json_encode([]), 'created_at' => $now, 'updated_at' => $now];

        DB::table('images')->insert($images);
    }
}
