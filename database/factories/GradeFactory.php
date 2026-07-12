<?php

namespace Database\Factories;

use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
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
            'evaluation_id' => Evaluation::factory(),
            'student_id' => Student::factory(),
            'score' => fake()->randomFloat(2, 0, 20),
            'status' => 'graded',
        ];
    }

    public function absent(): static
    {
        return $this->state(fn (array $attributes) => ['score' => null, 'status' => 'absent']);
    }

    public function notGraded(): static
    {
        return $this->state(fn (array $attributes) => ['score' => null, 'status' => 'not_graded']);
    }
}
