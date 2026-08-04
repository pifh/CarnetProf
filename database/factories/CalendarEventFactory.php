<?php

namespace Database\Factories;

use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarEvent>
 */
class CalendarEventFactory extends Factory
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
            'type' => CalendarEvent::TYPE_REUNION,
            'title' => fake()->sentence(3),
            'notes' => fake()->paragraph(),
            'starts_at' => fake()->dateTimeBetween('now', '+2 weeks'),
            'ends_at' => null,
            'all_day' => true,
        ];
    }
}
