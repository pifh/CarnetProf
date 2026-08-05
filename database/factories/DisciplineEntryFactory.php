<?php

namespace Database\Factories;

use App\Models\DisciplineEntry;
use App\Models\Student;
use App\Models\User;
use App\Support\DisciplineCategories;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DisciplineEntry>
 */
class DisciplineEntryFactory extends Factory
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
            'student_id' => Student::factory(),
            'category' => fake()->randomElement(DisciplineCategories::ALL),
            'occurred_at' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
        ];
    }
}
