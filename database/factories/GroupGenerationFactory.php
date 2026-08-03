<?php

namespace Database\Factories;

use App\Models\GroupGeneration;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupGeneration>
 */
class GroupGenerationFactory extends Factory
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
            'activity_name' => fake()->sentence(3),
            'mode' => 'count',
            'group_count' => 3,
            'groups' => [[], [], []],
            'criteria' => [
                'level_mode' => 'none',
                'gender_mode' => 'none',
                'avoid_repeats' => false,
                'excluded_student_ids' => [],
                'locked_placements' => [],
                'keep_together_pairs' => [],
                'keep_apart_pairs' => [],
                'conflicts' => [],
            ],
        ];
    }
}
