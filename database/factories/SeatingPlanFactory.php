<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\SeatingPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeatingPlan>
 */
class SeatingPlanFactory extends Factory
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
            'name' => 'Plan de classe',
        ];
    }
}
