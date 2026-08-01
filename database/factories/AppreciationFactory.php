<?php

namespace Database\Factories;

use App\Models\Appreciation;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appreciation>
 */
class AppreciationFactory extends Factory
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
            'school_class_id' => SchoolClass::factory(),
            'term_id' => Term::factory(),
            'type' => 'general',
            'content' => fake()->sentence(),
            'is_draft' => true,
        ];
    }

    public function disciplinary(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'disciplinary']);
    }

    public function final(): static
    {
        return $this->state(fn (array $attributes) => ['is_draft' => false]);
    }
}
