<?php

namespace Database\Factories;

use App\Models\SeatingPlanApplication;
use App\Models\SeatingPlanDesk;
use App\Models\SeatingPlanSeat;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeatingPlanSeat>
 */
class SeatingPlanSeatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seating_plan_application_id' => SeatingPlanApplication::factory(),
            'seating_plan_desk_id' => SeatingPlanDesk::factory(),
            'seat_index' => 0,
            'student_id' => Student::factory(),
        ];
    }
}
