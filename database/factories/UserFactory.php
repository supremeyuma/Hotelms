<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Assign a role through the Spatie permission tables.
     *
     * Roles are relations, not a users column, so they cannot be passed
     * through create([...]) mass assignment. The role is created on demand
     * because roles.slug is required and tests run against a fresh database.
     *
     * @param  string|\Spatie\Permission\Models\Role  $role
     */
    public function withRole($role): static
    {
        return $this->afterCreating(function ($user) use ($role) {
            $name = $role instanceof Role ? $role->name : (string) $role;

            $user->assignRole(
                Role::firstOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    ['slug' => Str::slug($name)]
                )
            );
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
