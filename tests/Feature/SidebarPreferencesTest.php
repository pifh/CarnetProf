<?php

use App\Filament\Navigation\UserNavigationManager;
use App\Filament\Pages\SidebarPreferences;
use App\Models\User;
use Livewire\Livewire;

it('orders items with a saved position before those without one, keeping ties stable', function () {
    expect(UserNavigationManager::comparePositions(0, 1))->toBeLessThan(0)
        ->and(UserNavigationManager::comparePositions(1, 0))->toBeGreaterThan(0)
        ->and(UserNavigationManager::comparePositions(null, 0))->toBeGreaterThan(0)
        ->and(UserNavigationManager::comparePositions(0, null))->toBeLessThan(0)
        ->and(UserNavigationManager::comparePositions(null, null))->toBe(0);
});

it('lists every navigation item for a teacher with no saved preferences, excluding its own item', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    $items = Livewire::test(SidebarPreferences::class)->get('data')['items'];
    $labels = collect($items)->pluck('label');

    expect($labels)->toContain('Classes')
        ->toContain('Élèves')
        ->not->toContain('Personnaliser le menu');

    expect(collect($items)->pluck('visible')->unique()->all())->toBe([true]);
});

it("saves the chosen order and hidden items to the teacher's account", function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    $component = Livewire::test(SidebarPreferences::class);
    $items = $component->get('data')['items'];

    // Hide the first item and move it to the end of the list, preserving
    // each row's own Repeater-assigned key (unset+reinsert moves a PHP
    // associative array entry to the end while keeping its key, mirroring
    // what the UI's reorder-with-buttons interaction actually produces).
    $firstKey = array_key_first($items);
    $hiddenItem = $items[$firstKey];
    $hiddenItem['visible'] = false;
    unset($items[$firstKey]);
    $items[$firstKey] = $hiddenItem;

    $component->set('data.items', $items)->call('save');

    $teacher->refresh();

    expect($teacher->sidebar_hidden)->toBe([$hiddenItem['key']])
        ->and($teacher->sidebar_order)->toBe(collect($items)->pluck('key')->all())
        ->and(array_key_last($teacher->sidebar_order) !== null)
        ->and(last($teacher->sidebar_order))->toBe($hiddenItem['key']);
});

it("hides an item from a teacher's rendered sidebar once they hide it", function () {
    $teacher = User::factory()->create([
        'sidebar_hidden' => [url('/school-classes')],
    ]);
    $this->actingAs($teacher);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertDontSee('href="'.url('/school-classes').'"', false);
});

it("reorders a teacher's rendered sidebar per their saved order", function () {
    $teacher = User::factory()->create([
        'sidebar_order' => [url('/students'), url('/school-classes')],
    ]);
    $this->actingAs($teacher);

    $content = $this->get('/')->getContent();

    $studentsPos = strpos($content, 'href="'.url('/students').'"');
    $classesPos = strpos($content, 'href="'.url('/school-classes').'"');

    expect($studentsPos)->not->toBeFalse()
        ->and($classesPos)->not->toBeFalse()
        ->and($studentsPos)->toBeLessThan($classesPos);
});

it('never hides its own settings page even if listed in sidebar_hidden', function () {
    $teacher = User::factory()->create([
        'sidebar_hidden' => [url('/sidebar-preferences')],
    ]);
    $this->actingAs($teacher);

    $response = $this->get('/');

    $response->assertSee('href="'.url('/sidebar-preferences').'"', false);
});

it("does not leak one teacher's sidebar preferences onto another's", function () {
    $teacherA = User::factory()->create(['sidebar_hidden' => [url('/school-classes')]]);
    $teacherB = User::factory()->create();

    $this->actingAs($teacherB);

    $response = $this->get('/');

    $response->assertSee('href="'.url('/school-classes').'"', false);
});
