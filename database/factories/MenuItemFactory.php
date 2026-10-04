<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_category_id' => MenuCategoryFactory::new(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 3, 60),
            'prep_time_minutes' => $this->faker->numberBetween(5, 45),
            'is_available' => true,
            'is_active' => true,
            'service_area' => $this->faker->randomElement(['restaurant', 'bar', 'room_service', 'pool']),
        ];
    }
}