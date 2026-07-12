<?php

namespace Database\Factories;

use App\Models\Evaluation;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evaluation>
 */
class EvaluationFactory extends Factory
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
            'term_id' => Term::factory(),
            'title' => fake()->randomElement(['Contrôle', 'Devoir maison', 'Interrogation']).' '.fake()->numberBetween(1, 5),
            'exam_date' => fake()->dateTimeBetween('-2 months', 'now'),
            'coefficient' => 1,
            'max_score' => 20,
        ];
    }
}
