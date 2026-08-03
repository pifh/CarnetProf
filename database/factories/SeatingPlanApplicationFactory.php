<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\SeatingPlan;
use App\Models\SeatingPlanApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeatingPlanApplication>
 */
class SeatingPlanApplicationFactory extends Factory
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
            'seating_plan_id' => SeatingPlan::factory(),
            'school_class_id' => SchoolClass::factory(),
        ];
    }
}
