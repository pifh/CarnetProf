<?php

use App\Filament\Pages\CalendrierReglages;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('lazily generates a calendar token on first access', function () {
    $teacher = User::factory()->create();
    expect($teacher->calendar_token)->toBeNull();

    $this->actingAs($teacher);
    Livewire::test(CalendrierReglages::class);

    expect($teacher->fresh()->calendar_token)->not->toBeNull();
});

it('regenerates the calendar token, invalidating the old one', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    $original = $teacher->ensureCalendarToken();

    Livewire::test(CalendrierReglages::class)->call('regenerateToken');

    expect($teacher->fresh()->calendar_token)->not->toBe($original);
});

it('persists selected feed categories', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    Livewire::test(CalendrierReglages::class)
        ->set('categories', ['cours', 'vacances'])
        ->call('saveCategories');

    expect($teacher->fresh()->calendar_feed_categories)->toBe(['cours', 'vacances']);
});

it('persists selected display categories independently from feed categories', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    Livewire::test(CalendrierReglages::class)
        ->set('displayCategories', ['cours', 'vacances'])
        ->call('saveDisplayCategories');

    expect($teacher->fresh()->calendar_display_categories)->toBe(['cours', 'vacances'])
        ->and($teacher->fresh()->calendar_feed_categories)->toBeNull();
});

it('saves the Ecole-Directe ICS url encrypted at rest and round-trips correctly', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    Livewire::test(CalendrierReglages::class)
        ->set('ecoleDirecteIcsUrl', 'https://ecole-directe.test/calendar.ics')
        ->call('saveEcoleDirecteUrl')
        ->assertHasNoErrors();

    $raw = DB::table('users')->where('id', $teacher->id)->value('ecole_directe_ics_url');
    expect($raw)->not->toContain('ecole-directe.test');

    expect($teacher->fresh()->ecole_directe_ics_url)->toBe('https://ecole-directe.test/calendar.ics');
});
