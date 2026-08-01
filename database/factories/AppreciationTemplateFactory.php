<?php

namespace Database\Factories;

use App\Models\AppreciationTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppreciationTemplate>
 */
class AppreciationTemplateFactory extends Factory
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
            'label' => fake()->words(3, true),
            'content' => fake()->sentence(),
        ];
    }
}
