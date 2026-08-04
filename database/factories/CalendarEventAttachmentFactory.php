<?php

namespace Database\Factories;

use App\Models\CalendarEvent;
use App\Models\CalendarEventAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarEventAttachment>
 */
class CalendarEventAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'calendar_event_id' => CalendarEvent::factory(),
            'path' => 'calendar-events/'.fake()->uuid().'.jpg',
            'original_filename' => fake()->word().'.jpg',
        ];
    }
}
