<?php

namespace Database\Factories;

use App\Models\BackupDestination;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BackupDestination>
 */
class BackupDestinationFactory extends Factory
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
            'provider' => 'ftp',
            'label' => fake()->words(2, true),
            'credentials' => [
                'host' => fake()->domainName(),
                'username' => fake()->userName(),
                'password' => fake()->password(),
            ],
            'is_active' => true,
            'last_used_at' => null,
        ];
    }

    public function siteWide(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }
}
