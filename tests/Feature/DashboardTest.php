<?php

use App\Filament\Widgets\ActiveClassesOverview;
use App\Filament\Widgets\DashboardStatsOverview;
use App\Filament\Widgets\PersonalReminders;
use App\Filament\Widgets\TodaysBirthdays;
use App\Models\Reminder;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

function widgetStats(object $widget): array
{
    $method = new ReflectionMethod($widget, 'getStats');
    $method->setAccessible(true);

    return collect($method->invoke($widget))
        ->mapWithKeys(fn ($stat) => [(string) $stat->getLabel() => $stat->getValue()])
        ->all();
}

it('counts only the acting teacher\'s active classes, students and birthdays today', function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();

    $activeClass = SchoolClass::factory()->for($teacher)->create();
    SchoolClass::factory()->for($teacher)->create(['is_archived' => true, 'archived_at' => now()]);
    SchoolClass::factory()->for($otherTeacher)->create();

    Student::factory()->for($teacher)->for($activeClass, 'schoolClass')->create([
        'birth_date' => Carbon::today()->subYears(11),
    ]);
    Student::factory()->for($teacher)->for($activeClass, 'schoolClass')->create([
        'birth_date' => Carbon::today()->subYears(12)->subDay(),
    ]);
    Student::factory()->for($otherTeacher)->for(
        SchoolClass::factory()->for($otherTeacher)->create(), 'schoolClass'
    )->create(['birth_date' => Carbon::today()->subYears(10)]);

    $this->actingAs($teacher);

    $stats = widgetStats(new DashboardStatsOverview);

    expect($stats['Classes actives'])->toBe(1)
        ->and($stats['Élèves actifs'])->toBe(2)
        ->and($stats['Anniversaires aujourd\'hui'])->toBe(1);
});

it('only shows students whose birthday is today, active and belonging to the teacher', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();

    $todayBirthday = Student::factory()->for($teacher)->for($class, 'schoolClass')->create([
        'first_name' => 'Camille',
        'last_name' => 'Dupont',
        'birth_date' => Carbon::today()->subYears(11),
    ]);
    $otherDay = Student::factory()->for($teacher)->for($class, 'schoolClass')->create([
        'birth_date' => Carbon::today()->addDay()->subYears(11),
    ]);
    $archivedTodayBirthday = Student::factory()->for($teacher)->for($class, 'schoolClass')->archived()->create([
        'birth_date' => Carbon::today()->subYears(13),
    ]);

    $this->actingAs($teacher);

    Livewire::test(TodaysBirthdays::class)
        ->assertCanSeeTableRecords([$todayBirthday])
        ->assertCanNotSeeTableRecords([$otherDay, $archivedTodayBirthday]);
});

it('lists only the teacher\'s own active classes with their active student count', function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();

    $class = SchoolClass::factory()->for($teacher)->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->archived()->create();

    $archivedClass = SchoolClass::factory()->for($teacher)->create(['is_archived' => true, 'archived_at' => now()]);
    SchoolClass::factory()->for($otherTeacher)->create();

    $this->actingAs($teacher);

    Livewire::test(ActiveClassesOverview::class)
        ->assertCanSeeTableRecords([$class])
        ->assertCanNotSeeTableRecords([$archivedClass])
        ->assertCountTableRecords(1);
});

it('lets a teacher manage their own personal reminders', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    Livewire::test(PersonalReminders::class)
        ->callTableAction('create', data: ['title' => 'Corriger les copies'])
        ->assertHasNoTableActionErrors();

    $reminder = Reminder::query()->where('user_id', $teacher->id)->first();
    expect($reminder->title)->toBe('Corriger les copies')
        ->and($reminder->is_done)->toBeFalse();
});

it('only shows a teacher their own reminders', function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();

    $ownReminder = Reminder::factory()->for($teacher)->create();
    $otherReminder = Reminder::factory()->for($otherTeacher)->create();

    $this->actingAs($teacher);

    Livewire::test(PersonalReminders::class)
        ->assertCanSeeTableRecords([$ownReminder])
        ->assertCanNotSeeTableRecords([$otherReminder]);
});
