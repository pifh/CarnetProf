<?php

namespace Database\Factories;

use App\Models\LogbookEntry;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LogbookEntry>
 */
class LogbookEntryFactory extends Factory
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
            'date' => fake()->dateTimeBetween('-1 month', 'now'),
            'content' => fake()->sentence(8),
        ];
    }

    public function withHomework(): static
    {
        return $this->state(fn (array $attributes) => [
            'homework' => fake()->sentence(6),
            'homework_due_date' => fake()->dateTimeBetween('now', '+2 weeks'),
        ]);
    }
}
