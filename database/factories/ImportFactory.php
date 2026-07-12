<?php

namespace Database\Factories;

use App\Models\Import;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Import>
 */
class ImportFactory extends Factory
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
            'original_filename' => 'eleves.csv',
            'column_mapping' => ['first_name' => 0, 'last_name' => 1],
            'status' => 'completed',
            'total_rows' => 0,
            'imported_rows' => 0,
            'duplicate_rows' => 0,
            'error_rows' => 0,
        ];
    }
}
