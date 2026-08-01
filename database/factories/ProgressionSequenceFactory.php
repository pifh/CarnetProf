<?php

namespace Database\Factories;

use App\Models\ProgressionSequence;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgressionSequence>
 */
class ProgressionSequenceFactory extends Factory
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
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'position' => 0,
            'status' => 'not_started',
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'in_progress']);
    }

    public function done(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'done', 'completed_at' => now()]);
    }
}
