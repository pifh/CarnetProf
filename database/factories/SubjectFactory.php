<?php

namespace Database\Factories;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
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
            // Suffixed with a random number: the base names are a small fixed
            // pool, and (user_id, name) is unique in the database — without a
            // disambiguator, two subjects for the same teacher collide often
            // enough to make tests that create several flaky.
            'name' => fake()->randomElement(['Mathématiques', 'Français', 'Histoire-Géographie', 'Anglais', 'SVT', 'Physique-Chimie', 'EPS']).' '.fake()->numberBetween(1, 999999),
            'color' => fake()->hexColor(),
        ];
    }
}
