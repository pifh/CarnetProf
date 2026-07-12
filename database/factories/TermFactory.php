<?php

namespace Database\Factories;

use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Term>
 */
class TermFactory extends Factory
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
            'school_year' => Term::currentSchoolYear(),
            'label' => 'Trimestre 1',
            'position' => 1,
        ];
    }
}
