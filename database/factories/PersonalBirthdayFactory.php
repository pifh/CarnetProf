<?php

namespace Database\Factories;

use App\Models\PersonalBirthday;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonalBirthday>
 */
class PersonalBirthdayFactory extends Factory
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
            'name' => fake()->name(),
            'date' => fake()->dateTimeBetween('-60 years', '-1 years'),
            'notes' => null,
        ];
    }
}
