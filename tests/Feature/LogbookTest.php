<?php

use App\Filament\Resources\LogbookEntries\Pages\ListLogbookEntries;
use App\Filament\Widgets\UpcomingHomework;
use App\Models\LogbookEntry;
use App\Models\ProgressionSequence;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

it("only lists a teacher's own logbook entries", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();

    $class = SchoolClass::factory()->for($teacher)->create();
    $ownEntry = LogbookEntry::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    LogbookEntry::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();

    $this->actingAs($teacher);

    Livewire::test(ListLogbookEntries::class)
        ->assertCanSeeTableRecords([$ownEntry])
        ->assertCountTableRecords(1);
});

it("prevents a teacher from updating or deleting another teacher's logbook entry", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    $entry = LogbookEntry::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();

    expect($teacher->can('update', $entry))->toBeFalse()
        ->and($teacher->can('delete', $entry))->toBeFalse();
});

it('only shows upcoming homework due today or later, for the current teacher', function () {
    Carbon::setTestNow(Carbon::create(2026, 7, 12));

    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();

    $upcoming = LogbookEntry::factory()->for($teacher)->for($class, 'schoolClass')->withHomework()->create(['homework_due_date' => '2026-07-15']);
    $today = LogbookEntry::factory()->for($teacher)->for($class, 'schoolClass')->withHomework()->create(['homework_due_date' => '2026-07-12']);
    $past = LogbookEntry::factory()->for($teacher)->for($class, 'schoolClass')->withHomework()->create(['homework_due_date' => '2026-07-01']);
    $noHomework = LogbookEntry::factory()->for($teacher)->for($class, 'schoolClass')->create(['homework' => null, 'homework_due_date' => null]);

    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    LogbookEntry::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->withHomework()->create(['homework_due_date' => '2026-07-20']);

    $this->actingAs($teacher);

    Livewire::test(UpcomingHomework::class)
        ->assertCanSeeTableRecords([$upcoming, $today])
        ->assertCanNotSeeTableRecords([$past, $noHomework])
        ->assertCountTableRecords(2);

    Carbon::setTestNow();
});

it('links a logbook entry to a progression sequence of the same class', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $sequence = ProgressionSequence::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $entry = LogbookEntry::factory()->for($teacher)->for($class, 'schoolClass')->create(['progression_sequence_id' => $sequence->id]);

    expect($entry->progressionSequence->id)->toBe($sequence->id);
});

it('keeps logbook entries and their linked sequences separate per subject', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $maths = Subject::factory()->for($teacher)->create(['name' => 'Mathématiques']);
    $informatique = Subject::factory()->for($teacher)->create(['name' => 'Informatique']);
    $class->subjects()->attach([$maths->id, $informatique->id]);

    $mathsSequence = ProgressionSequence::factory()->for($teacher)->for($class, 'schoolClass')->for($maths)->create();
    $infoSequence = ProgressionSequence::factory()->for($teacher)->for($class, 'schoolClass')->for($informatique)->create();

    $mathsEntry = LogbookEntry::factory()->for($teacher)->for($class, 'schoolClass')->for($maths)->create(['progression_sequence_id' => $mathsSequence->id]);
    $infoEntry = LogbookEntry::factory()->for($teacher)->for($class, 'schoolClass')->for($informatique)->create(['progression_sequence_id' => $infoSequence->id]);

    expect($mathsEntry->subject->id)->toBe($maths->id)
        ->and($mathsEntry->progressionSequence->id)->toBe($mathsSequence->id)
        ->and($infoEntry->subject->id)->toBe($informatique->id)
        ->and($infoEntry->progressionSequence->id)->toBe($infoSequence->id);
});
