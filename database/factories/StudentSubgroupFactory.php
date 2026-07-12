<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\StudentSubgroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentSubgroup>
 */
class StudentSubgroupFactory extends Factory
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
            'school_class_id' => SchoolClass::factory(),
            'name' => fake()->randomElement(['Groupe rouge', 'Groupe bleu', 'Groupe vert', 'Atelier 1', 'Atelier 2']),
            'color' => fake()->hexColor(),
        ];
    }
}
