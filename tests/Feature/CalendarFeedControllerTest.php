<?php

use App\Models\CalendarEvent;
use App\Models\User;

it('serves a valid teacher\'s ICS feed without any authenticated session', function () {
    $teacher = User::factory()->create();
    $token = $teacher->ensureCalendarToken();
    CalendarEvent::factory()->for($teacher)->create(['title' => 'Réunion test']);

    $response = $this->get("/calendar/{$token}.ics");

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

    expect($response->getContent())
        ->toContain('BEGIN:VCALENDAR')
        ->toContain('Réunion test');
});

it('returns 404 for an invalid token', function () {
    $this->get('/calendar/not-a-real-token.ics')->assertNotFound();
});

it("never leaks another teacher's events in the feed", function () {
    $teacherA = User::factory()->create();
    $teacherB = User::factory()->create();
    $token = $teacherA->ensureCalendarToken();

    CalendarEvent::factory()->for($teacherB)->create(['title' => 'Secret de B']);

    $response = $this->get("/calendar/{$token}.ics");

    expect($response->getContent())->not->toContain('Secret de B');
});
