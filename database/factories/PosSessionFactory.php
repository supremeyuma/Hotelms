<?php

namespace Database\Factories;

use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PosSession>
 */
class PosSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'device_id' => PosDevice::factory(),
            'user_id' => User::factory(),
            'opened_at' => now(),
            'cash_start' => 0,
            'status' => 'open',
        ];
    }
}