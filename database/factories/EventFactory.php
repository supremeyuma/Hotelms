<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('+1 day', '+60 days');

        return [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'start_time' => $start,
            'end_time' => (clone $start)->modify('+' . $this->faker->numberBetween(2, 8) . ' hours'),
            'venue' => $this->faker->randomElement(['Main Hall', 'Garden Terrace', 'Conference Room A']),
            'is_active' => true,
            'is_featured' => false,
            'status' => 'published',
        ];
    }
}