<?php

namespace Database\Seeders;

use App\Models\PosDevice;
use Illuminate\Database\Seeder;

class PosDeviceSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = config('pos.devices.default');

        PosDevice::firstOrCreate(
            ['device_code' => $defaults['code']],
            [
                'device_name' => $defaults['name'],
                'device_type' => $defaults['type'],
                'is_active' => true,
                'metadata' => ['registered_via' => 'seeder'],
            ]
        );
    }
}