<?php

namespace Database\Factories;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GuestRequest>
 */
class GuestRequestFactory extends Factory
{
    /**
     * booking_id and room_id are both non-null and constrained, so the
     * factory always derives room from the booking it creates.
     */
    public function definition(): array
    {
        $booking = Booking::factory()->create();

        return [
            'booking_id' => $booking->id,
            'room_id' => $booking->room_id,
            'type' => $this->faker->randomElement(['cleaning', 'laundry', 'kitchen', 'bar', 'maintenance']),
            'status' => $this->faker->randomElement(['pending', 'acknowledged', 'completed']),
            'notes' => $this->faker->optional()->sentence(),
            'requestable_type' => null,
            'requestable_id' => null,
        ];
    }
}
