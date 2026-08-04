<?php

namespace Database\Factories;

use App\Models\EcoleDirecteEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EcoleDirecteEvent>
 */
class EcoleDirecteEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-5 days', '+5 days');

        return [
            'user_id' => User::factory(),
            'uid' => fake()->uuid(),
            'title' => fake()->randomElement(['Mathématiques 6e A', 'Français 5e B', 'Histoire-Géo 4e C']),
            'starts_at' => $start,
            'ends_at' => (clone $start)->modify('+55 minutes'),
        ];
    }
}
