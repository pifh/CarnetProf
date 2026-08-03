<?php

namespace Database\Factories;

use App\Models\RandomPickSession;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RandomPickSession>
 */
class RandomPickSessionFactory extends Factory
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
            'name' => 'Interrogation orale',
        ];
    }
}
