<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['6e A', '6e B', '5e A', '4e C', '3e B', 'Terminale S1']),
            'level' => fake()->randomElement(['6e', '5e', '4e', '3e', 'Terminale']),
            'school_year' => SchoolClass::currentSchoolYear(),
            'color' => fake()->hexColor(),
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_archived' => true,
            'archived_at' => now(),
        ]);
    }
}
