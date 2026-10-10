<?php

namespace Database\Factories;

use App\Models\PosDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PosDevice>
 */
class PosDeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'device_code' => 'POS-' . strtoupper($this->faker->unique()->lexify('????????')),
            'device_name' => $this->faker->company() . ' Terminal',
            'device_type' => 'club_terminal',
            'is_active' => true,
        ];
    }
}