<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkIn = $this->faker->dateTimeBetween('+1 day', '+30 days');

        return [
            'property_id' => Property::factory(),
            'room_id' => Room::factory(),
            'user_id' => User::factory(),
            'booking_code' => strtoupper($this->faker->bothify('BK-####??')),
            'check_in' => $checkIn,
            'check_out' => (clone $checkIn)->modify('+' . $this->faker->numberBetween(1, 7) . ' days'),
            'guests' => $this->faker->numberBetween(1, 4),
            'adults' => $this->faker->numberBetween(1, 4),
            'children' => 0,
            'total_amount' => $this->faker->randomFloat(2, 100, 3000),
            'nightly_rate' => $this->faker->randomFloat(2, 50, 800),
            'status' => 'pending',
            'payment_method' => $this->faker->randomElement(['cash', 'card', 'online']),
            'payment_status' => 'unpaid',
        ];
    }
}