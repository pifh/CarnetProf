<?php

use App\Livewire\StudentRecordCard;
use App\Models\Appreciation;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentEvent;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\DisciplineTracker;
use Livewire\Livewire;

it('defaults to the school class passed in, or the student\'s home class', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $this->actingAs($teacher);

    $component = Livewire::test(StudentRecordCard::class, ['studentId' => $student->id]);

    expect($component->get('schoolClassId'))->toBe($class->id);
});

it('falls back to the home class when an invalid schoolClassId is passed', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $otherClass = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $this->actingAs($teacher);

    $component = Livewire::test(StudentRecordCard::class, [
        'studentId' => $student->id,
        'schoolClassId' => $otherClass->id,
    ]);

    expect($component->get('schoolClassId'))->toBe($class->id);
});

it('shows a subject-by-subject average plus an overall average for a multi-subject class', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $maths = Subject::factory()->for($teacher)->create(['name' => 'Mathématiques']);
    $french = Subject::factory()->for($teacher)->create(['name' => 'Français']);
    $class->subjects()->attach([$maths->id, $french->id]);
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $mathsEval = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->for($maths)->create(['max_score' => 20]);
    $frenchEval = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->for($french)->create(['max_score' => 20]);
    Grade::factory()->for($teacher)->for($mathsEval)->for($student)->create(['score' => 10, 'status' => 'graded']);
    Grade::factory()->for($teacher)->for($frenchEval)->for($student)->create(['score' => 16, 'status' => 'graded']);

    $this->actingAs($teacher);

    $component = Livewire::test(StudentRecordCard::class, [
        'studentId' => $student->id,
        'schoolClassId' => $class->id,
        'defaultTermId' => $term->id,
    ]);

    $rows = $component->get('subjectAverages');

    expect($rows)->toHaveCount(3)
        ->and($rows->firstWhere('label', 'Mathématiques')['average'])->toBe(10.0)
        ->and($rows->firstWhere('label', 'Français')['average'])->toBe(16.0)
        ->and($rows->firstWhere('label', 'Moyenne générale')['average'])->toBe(13.0);
});

it('shows a single average row for a class with one or no subjects', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $evaluation = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create(['max_score' => 20]);
    Grade::factory()->for($teacher)->for($evaluation)->for($student)->create(['score' => 14, 'status' => 'graded']);

    $this->actingAs($teacher);

    $component = Livewire::test(StudentRecordCard::class, [
        'studentId' => $student->id,
        'schoolClassId' => $class->id,
        'defaultTermId' => $term->id,
    ]);

    $rows = $component->get('subjectAverages');

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['average'])->toBe(14.0);
});

it('computes the average for the full year when no term is selected', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $termA = Term::factory()->for($teacher)->create(['position' => 1]);
    $termB = Term::factory()->for($teacher)->create(['position' => 2]);
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $evalA = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($termA)->create(['max_score' => 20]);
    $evalB = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($termB)->create(['max_score' => 20]);
    Grade::factory()->for($teacher)->for($evalA)->for($student)->create(['score' => 10, 'status' => 'graded']);
    Grade::factory()->for($teacher)->for($evalB)->for($student)->create(['score' => 20, 'status' => 'graded']);

    $this->actingAs($teacher);

    $component = Livewire::test(StudentRecordCard::class, [
        'studentId' => $student->id,
        'schoolClassId' => $class->id,
        'defaultTermId' => null,
    ]);

    expect($component->get('subjectAverages')->first()['average'])->toBe(15.0);
});

it('exposes the individual grades behind each subject average, with the evaluation for the tooltip', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $evaluation = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create([
        'title' => 'Contrôle 1', 'max_score' => 20, 'coefficient' => 2,
    ]);
    Grade::factory()->for($teacher)->for($evaluation)->for($student)->create(['score' => 14, 'status' => 'graded']);

    $this->actingAs($teacher);

    $component = Livewire::test(StudentRecordCard::class, [
        'studentId' => $student->id,
        'schoolClassId' => $class->id,
        'defaultTermId' => $term->id,
    ]);

    $grades = $component->get('subjectAverages')->first()['grades'];

    expect($grades)->toHaveCount(1)
        ->and((float) $grades->first()->score)->toBe(14.0)
        ->and($grades->first()->evaluation->title)->toBe('Contrôle 1')
        ->and((float) $grades->first()->evaluation->coefficient)->toBe(2.0);
});

it('exposes discipline entry dates grouped by category', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $this->actingAs($teacher);

    $tracker = app(DisciplineTracker::class);
    $tracker->logEntry($student, $class, 'bavardage');
    $tracker->logEntry($student, $class, 'oubli_materiel');

    $component = Livewire::test(StudentRecordCard::class, [
        'studentId' => $student->id,
        'schoolClassId' => $class->id,
    ]);

    $entries = $component->get('disciplineEntries');

    expect($entries->get('bavardage'))->toHaveCount(1)
        ->and($entries->get('oubli_materiel'))->toHaveCount(1)
        ->and($entries->get('bavardage')->first()->occurred_at)->not->toBeNull();
});

it('keeps discipline counts independent between a real class and a groupe classe', function () {
    $teacher = User::factory()->create();
    $realClass = SchoolClass::factory()->for($teacher)->create();
    $groupClass = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($realClass, 'schoolClass')->create();
    $student->groupClasses()->attach($groupClass);
    $this->actingAs($teacher);

    $tracker = app(DisciplineTracker::class);
    $tracker->logEntry($student, $realClass, 'bavardage');
    $tracker->logEntry($student, $groupClass, 'bavardage');
    $tracker->logEntry($student, $groupClass, 'bavardage');

    $realComponent = Livewire::test(StudentRecordCard::class, [
        'studentId' => $student->id,
        'schoolClassId' => $realClass->id,
    ]);
    $groupComponent = Livewire::test(StudentRecordCard::class, [
        'studentId' => $student->id,
        'schoolClassId' => $groupClass->id,
        'allowClassSwitch' => true,
    ]);

    expect($realComponent->get('disciplineCounts')['bavardage'])->toBe(['total' => 1, 'trip' => 1])
        ->and($groupComponent->get('disciplineCounts')['bavardage'])->toBe(['total' => 2, 'trip' => 2]);
});

it('saves a single appreciation, with a subject when the class has one', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $subject = Subject::factory()->for($teacher)->create();
    $class->subjects()->attach($subject->id);
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $this->actingAs($teacher);

    Livewire::test(StudentRecordCard::class, [
        'studentId' => $student->id,
        'schoolClassId' => $class->id,
        'defaultTermId' => $term->id,
    ])->call('updateAppreciation', 'Bon travail.');

    $appreciation = Appreciation::query()->where('student_id', $student->id)->first();

    expect($appreciation->content)->toBe('Bon travail.')
        ->and($appreciation->subject_id)->toBe($subject->id)
        ->and($appreciation->term_id)->toBe($term->id)
        ->and($appreciation->user_id)->toBe($teacher->id);
});

it('keeps the appreciation read-only when appreciationReadOnly is set (Événements élèves embed)', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $appreciation = Appreciation::factory()->for($teacher)->for($student)->for($class, 'schoolClass')->for($term)->create([
        'content' => 'Déjà rédigée.',
        'is_draft' => true,
    ]);
    $this->actingAs($teacher);

    Livewire::test(StudentRecordCard::class, [
        'studentId' => $student->id,
        'schoolClassId' => $class->id,
        'defaultTermId' => $term->id,
        'appreciationReadOnly' => true,
    ])
        ->assertSee('Déjà rédigée.')
        ->call('updateAppreciation', 'Tentative de modification.')
        ->call('toggleAppreciationDraft');

    expect($appreciation->refresh()->content)->toBe('Déjà rédigée.')
        ->and($appreciation->is_draft)->toBeTrue();
});

it('renders the appréciation as read-only text, without an editable textarea, when embedded in the events form', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $this->actingAs($teacher);

    Livewire::test(StudentRecordCard::class, [
        'studentId' => $student->id,
        'schoolClassId' => $class->id,
        'appreciationReadOnly' => true,
    ])
        ->assertDontSeeHtml('wire:change="updateAppreciation($event.target.value)"')
        ->assertDontSeeHtml('wire:click="toggleAppreciationDraft"');
});

it('toggles the draft state of an appreciation', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    Appreciation::factory()->for($teacher)->for($student)->for($class, 'schoolClass')->for($term)->create(['is_draft' => true]);
    $this->actingAs($teacher);

    Livewire::test(StudentRecordCard::class, [
        'studentId' => $student->id,
        'schoolClassId' => $class->id,
        'defaultTermId' => $term->id,
    ])->call('toggleAppreciationDraft');

    expect(Appreciation::query()->where('student_id', $student->id)->first()->is_draft)->toBeFalse();
});

it('saves a year-level appreciation (term_id null) when no term is selected (année complète)', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $this->actingAs($teacher);

    Livewire::test(StudentRecordCard::class, [
        'studentId' => $student->id,
        'schoolClassId' => $class->id,
        'defaultTermId' => null,
    ])->call('updateAppreciation', 'Belle année.');

    $appreciation = Appreciation::query()->where('student_id', $student->id)->first();

    expect($appreciation)->not->toBeNull()
        ->and($appreciation->term_id)->toBeNull()
        ->and($appreciation->content)->toBe('Belle année.');
});

it("refuses to load another teacher's student", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    $otherStudent = Student::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();
    $this->actingAs($teacher);

    $component = Livewire::test(StudentRecordCard::class, ['studentId' => $otherStudent->id]);

    expect($component->get('student'))->toBeNull();
});

it('limits recent events to the 8 most recent, newest first', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $this->actingAs($teacher);

    foreach (range(1, 10) as $i) {
        StudentEvent::factory()->for($teacher)->for($student)->create(['starts_at' => now()->subDays($i)]);
    }

    $component = Livewire::test(StudentRecordCard::class, ['studentId' => $student->id]);
    $events = $component->get('recentEvents');

    expect($events)->toHaveCount(8)
        ->and($events->first()->starts_at->isAfter($events->last()->starts_at))->toBeTrue();
});
