<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudentEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentEvent>
 */
class StudentEventFactory extends Factory
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
            'type' => fake()->randomElement(['Réunion parents', 'Avertissement', 'Rencontre mensuelle']),
            'event_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'notes' => fake()->sentence(),
        ];
    }
}
