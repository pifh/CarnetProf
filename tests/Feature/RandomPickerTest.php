<?php

use App\Filament\Pages\RandomPicker;
use App\Models\RandomPick;
use App\Models\RandomPickSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Livewire\Livewire;

it('creates a named tirage for a class and opens it', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(RandomPicker::class)
        ->set('schoolClassId', $class->id)
        ->set('newSessionName', 'Interrogation orale')
        ->call('createSession');

    $session = RandomPickSession::query()->first();

    expect($session->name)->toBe('Interrogation orale')
        ->and($session->school_class_id)->toBe($class->id)
        ->and($session->user_id)->toBe($teacher->id)
        ->and($component->get('currentSessionId'))->toBe($session->id);
});

it('refuses to create a tirage without a name', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    Livewire::test(RandomPicker::class)
        ->set('schoolClassId', $class->id)
        ->set('newSessionName', '')
        ->call('createSession');

    expect(RandomPickSession::query()->count())->toBe(0);
});

it('picks a student and records the draw against the open session', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(RandomPicker::class)
        ->set('schoolClassId', $class->id)
        ->set('newSessionName', 'Interrogation orale')
        ->call('createSession')
        ->call('pick');

    expect($component->get('lastPickedStudentId'))->toBe($student->id);

    $pick = RandomPick::query()->first();

    expect($pick->student_id)->toBe($student->id)
        ->and($pick->school_class_id)->toBe($class->id)
        ->and($pick->random_pick_session_id)->toBe($component->get('currentSessionId'))
        ->and($pick->user_id)->toBe($teacher->id);
});

it('does not pick anything before a tirage has been created or opened', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    Livewire::test(RandomPicker::class)
        ->set('schoolClassId', $class->id)
        ->call('pick');

    expect(RandomPick::query()->count())->toBe(0);
});

it('does not repeat a student until every eligible student has been picked in the session', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->count(3)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(RandomPicker::class)
        ->set('schoolClassId', $class->id)
        ->set('newSessionName', 'Interrogation orale')
        ->call('createSession');

    $picked = [];
    foreach (range(1, 3) as $i) {
        $component->call('pick');
        $picked[] = $component->get('lastPickedStudentId');
    }

    expect(array_unique($picked))->toHaveCount(3)
        ->and($component->get('pickedStudentIdsThisRound'))->toHaveCount(3);

    $component->call('pick');

    expect($component->get('pickedStudentIdsThisRound'))->toHaveCount(1);
});

it('excludes marked students from the draw', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $absent = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $present = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(RandomPicker::class)
        ->set('schoolClassId', $class->id)
        ->set('newSessionName', 'Interrogation orale')
        ->call('createSession')
        ->call('toggleExcluded', $absent->id);

    foreach (range(1, 5) as $i) {
        $component->call('pick');
        expect($component->get('lastPickedStudentId'))->toBe($present->id);
    }
});

it('resets the round', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(RandomPicker::class)
        ->set('schoolClassId', $class->id)
        ->set('newSessionName', 'Interrogation orale')
        ->call('createSession')
        ->call('pick')
        ->call('resetRound');

    expect($component->get('lastPickedStudentId'))->toBeNull()
        ->and($component->get('pickedStudentIdsThisRound'))->toBe([]);
});

it('resuming a tirage seeds the round from the students already picked in it', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $studentA = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $studentB = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $session = RandomPickSession::factory()->for($teacher)->for($class, 'schoolClass')->create();
    RandomPick::factory()->for($teacher)->for($studentA)->for($class, 'schoolClass')->create(['random_pick_session_id' => $session->id]);

    $this->actingAs($teacher);

    $component = Livewire::test(RandomPicker::class)
        ->set('schoolClassId', $class->id)
        ->call('openSession', $session->id);

    expect($component->get('pickedStudentIdsThisRound'))->toBe([$studentA->id])
        ->and($component->get('currentSessionId'))->toBe($session->id);

    $component->call('pick');

    // studentA was already picked in a previous visit, so the fresh pick
    // must be studentB rather than repeating studentA.
    expect($component->get('lastPickedStudentId'))->toBe($studentB->id);
});

it('keeps two tirages for the same class independent from each other', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $sessionA = RandomPickSession::factory()->for($teacher)->for($class, 'schoolClass')->create(['name' => 'Tirage A']);
    $sessionB = RandomPickSession::factory()->for($teacher)->for($class, 'schoolClass')->create(['name' => 'Tirage B']);
    RandomPick::factory()->for($teacher)->for($student)->for($class, 'schoolClass')->count(3)->create(['random_pick_session_id' => $sessionA->id]);

    $this->actingAs($teacher);

    $componentA = Livewire::test(RandomPicker::class)
        ->set('schoolClassId', $class->id)
        ->call('openSession', $sessionA->id);

    expect($componentA->get('sessionHistory')->firstWhere('student.id', $student->id)['count'])->toBe(3);

    $componentB = Livewire::test(RandomPicker::class)
        ->set('schoolClassId', $class->id)
        ->call('openSession', $sessionB->id);

    expect($componentB->get('sessionHistory')->firstWhere('student.id', $student->id)['count'])->toBe(0);
});

it('lists existing tirages for the selected class, most recent first', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();

    $older = RandomPickSession::factory()->for($teacher)->for($class, 'schoolClass')->create(['name' => 'Ancien tirage', 'created_at' => now()->subDay()]);
    $newer = RandomPickSession::factory()->for($teacher)->for($class, 'schoolClass')->create(['name' => 'Nouveau tirage']);

    $this->actingAs($teacher);

    $sessions = Livewire::test(RandomPicker::class)
        ->set('schoolClassId', $class->id)
        ->get('sessions');

    expect($sessions->pluck('id')->all())->toBe([$newer->id, $older->id]);
});

it('scopes the tirage list to the selected subject when the class has several', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $maths = Subject::factory()->for($teacher)->create(['name' => 'Mathématiques']);
    $informatique = Subject::factory()->for($teacher)->create(['name' => 'Informatique']);
    $class->subjects()->attach([$maths->id, $informatique->id]);

    $mathsSession = RandomPickSession::factory()->for($teacher)->for($class, 'schoolClass')->for($maths)->create(['name' => 'Tirage maths']);
    RandomPickSession::factory()->for($teacher)->for($class, 'schoolClass')->for($informatique)->create(['name' => 'Tirage info']);

    $this->actingAs($teacher);

    $sessions = Livewire::test(RandomPicker::class)
        ->set('schoolClassId', $class->id)
        ->set('subjectId', $maths->id)
        ->get('sessions');

    expect($sessions->pluck('id')->all())->toBe([$mathsSession->id]);
});

it('deletes a tirage and its picks, closing it if it was open', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $session = RandomPickSession::factory()->for($teacher)->for($class, 'schoolClass')->create();
    RandomPick::factory()->for($teacher)->for($student)->for($class, 'schoolClass')->create(['random_pick_session_id' => $session->id]);

    $this->actingAs($teacher);

    $component = Livewire::test(RandomPicker::class)
        ->set('schoolClassId', $class->id)
        ->call('openSession', $session->id)
        ->call('deleteSession', $session->id);

    expect(RandomPickSession::query()->find($session->id))->toBeNull()
        ->and(RandomPick::query()->count())->toBe(0)
        ->and($component->get('currentSessionId'))->toBeNull();
});

it("prevents a teacher from updating or deleting another teacher's tirage", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    $session = RandomPickSession::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();

    expect($teacher->can('update', $session))->toBeFalse()
        ->and($teacher->can('delete', $session))->toBeFalse();
});

it("keeps the draw isolated from another teacher's students", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    Student::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();

    $this->actingAs($teacher);

    $students = Livewire::test(RandomPicker::class)
        ->set('schoolClassId', $class->id)
        ->get('students');

    expect($students)->toHaveCount(1)
        ->and($students->first()->id)->toBe($student->id);
});
