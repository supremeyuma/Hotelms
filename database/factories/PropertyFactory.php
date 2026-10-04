<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Property>
 */
class PropertyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' Hotel',
            'location' => $this->faker->address(),
            'amenities' => $this->faker->randomElements(
                ['wifi', 'pool', 'gym', 'parking', 'spa', 'restaurant'],
                $this->faker->numberBetween(1, 4)
            ),
        ];
    }
}