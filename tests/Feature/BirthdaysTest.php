<?php

use App\Filament\Pages\Birthdays;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;

it('only lists students whose birthday falls in the selected period', function () {
    Carbon::setTestNow(Carbon::create(2026, 7, 9));

    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();

    $today = Student::factory()->for($teacher)->for($class, 'schoolClass')->create([
        'birth_date' => Carbon::create(2013, 7, 9),
    ]);
    $inFiveDays = Student::factory()->for($teacher)->for($class, 'schoolClass')->create([
        'birth_date' => Carbon::create(2014, 7, 14),
    ]);
    $inThreeWeeks = Student::factory()->for($teacher)->for($class, 'schoolClass')->create([
        'birth_date' => Carbon::create(2014, 7, 28),
    ]);
    $lastMonth = Student::factory()->for($teacher)->for($class, 'schoolClass')->create([
        'birth_date' => Carbon::create(2014, 6, 1),
    ]);

    $this->actingAs($teacher);
    $page = new Birthdays;

    $page->period = 'day';
    expect($page->getStudents()->pluck('id')->all())->toBe([$today->id]);

    $page->period = 'week';
    expect($page->getStudents()->pluck('id')->all())->toBe([$today->id, $inFiveDays->id]);

    $page->period = 'month';
    expect($page->getStudents()->pluck('id')->all())->toBe([$today->id, $inFiveDays->id, $inThreeWeeks->id])
        ->and($page->getStudents()->pluck('id'))->not->toContain($lastMonth->id);

    Carbon::setTestNow();
});

it('wraps birthdays around the new year correctly', function () {
    Carbon::setTestNow(Carbon::create(2026, 12, 30));

    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();

    $newYearBirthday = Student::factory()->for($teacher)->for($class, 'schoolClass')->create([
        'birth_date' => Carbon::create(2014, 1, 2),
    ]);

    $this->actingAs($teacher);
    $page = new Birthdays;
    $page->period = 'week';

    expect($page->getStudents()->pluck('id')->all())->toBe([$newYearBirthday->id]);

    $student = $page->getStudents()->first();
    expect($student->next_birthday->year)->toBe(2027);

    Carbon::setTestNow();
});

it('hides archived students by default but can show them', function () {
    Carbon::setTestNow(Carbon::create(2026, 7, 9));

    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();

    $active = Student::factory()->for($teacher)->for($class, 'schoolClass')->create([
        'birth_date' => Carbon::create(2013, 7, 9),
    ]);
    $archived = Student::factory()->for($teacher)->for($class, 'schoolClass')->archived()->create([
        'birth_date' => Carbon::create(2013, 7, 9),
    ]);

    $this->actingAs($teacher);
    $page = new Birthdays;
    $page->period = 'day';

    expect($page->getStudents()->pluck('id')->all())->toBe([$active->id]);

    $page->showArchived = true;
    expect($page->getStudents()->pluck('id')->all())->toContain($archived->id);

    Carbon::setTestNow();
});

it('only shows a teacher their own students\' birthdays', function () {
    Carbon::setTestNow(Carbon::create(2026, 7, 9));

    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();

    $own = Student::factory()->for($teacher)->for($class, 'schoolClass')->create([
        'birth_date' => Carbon::create(2013, 7, 9),
    ]);
    Student::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create([
        'birth_date' => Carbon::create(2013, 7, 9),
    ]);

    $this->actingAs($teacher);
    $page = new Birthdays;
    $page->period = 'day';

    expect($page->getStudents()->pluck('id')->all())->toBe([$own->id]);

    Carbon::setTestNow();
});
