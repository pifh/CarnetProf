<?php

namespace Database\Factories;

use App\Models\SeatingPlan;
use App\Models\SeatingPlanDesk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeatingPlanDesk>
 */
class SeatingPlanDeskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seating_plan_id' => SeatingPlan::factory(),
            'position_row' => 0,
            'position_col' => 0,
            'capacity' => 2,
        ];
    }
}
