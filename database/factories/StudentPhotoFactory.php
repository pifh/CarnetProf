<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudentPhoto;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentPhoto>
 */
class StudentPhotoFactory extends Factory
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
            'path' => 'students/'.fake()->uuid().'.jpg',
            'school_year' => '2026-2027',
            'source' => 'manual',
            'is_current' => true,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => ['is_current' => false]);
    }
}
