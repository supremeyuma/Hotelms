<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RoomType>
 */
class RoomTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'title' => $this->faker->unique()->words(2, true),
            'max_occupancy' => $this->faker->numberBetween(1, 6),
            'base_price' => $this->faker->randomFloat(2, 50, 800),
            'features' => $this->faker->randomElements(
                ['wifi', 'tv', 'minibar', 'aircon', 'balcony'],
                $this->faker->numberBetween(1, 3)
            ),
        ];
    }
}