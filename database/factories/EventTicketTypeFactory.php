<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventTicketType>
 */
class EventTicketTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => $this->faker->randomElement(['General Admission', 'VIP', 'Balcony', 'Student']),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 10, 500),
            'quantity_available' => $this->faker->numberBetween(20, 200),
            'quantity_sold' => 0,
            'max_per_person' => $this->faker->numberBetween(2, 10),
            'is_active' => true,
        ];
    }
}