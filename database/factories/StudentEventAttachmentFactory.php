<?php

namespace Database\Factories;

use App\Models\StudentEvent;
use App\Models\StudentEventAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentEventAttachment>
 */
class StudentEventAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_event_id' => StudentEvent::factory(),
            'path' => 'student-events/'.fake()->uuid().'.jpg',
            'original_filename' => fake()->word().'.jpg',
        ];
    }
}
