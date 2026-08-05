<?php

namespace Database\Factories;

use App\Models\DisciplineReset;
use App\Models\Student;
use App\Models\User;
use App\Support\DisciplineCategories;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DisciplineReset>
 */
class DisciplineResetFactory extends Factory
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
            'last_entry_id' => null,
        ];
    }
}
