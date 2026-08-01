<?php

use App\Filament\Pages\Appreciations;
use App\Filament\Resources\AppreciationTemplates\Pages\ListAppreciationTemplates;
use App\Models\Appreciation;
use App\Models\AppreciationTemplate;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Livewire\Livewire;

it('lets a teacher write and save an appreciation', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    Livewire::test(Appreciations::class)
        ->set('schoolClassId', $class->id)
        ->set('termId', $term->id)
        ->call('updateContent', $student->id, 'Bon trimestre, continuez ainsi.');

    $appreciation = Appreciation::query()->where('student_id', $student->id)->first();

    expect($appreciation)->not->toBeNull()
        ->and($appreciation->content)->toBe('Bon trimestre, continuez ainsi.')
        ->and($appreciation->type)->toBe('general')
        ->and($appreciation->is_draft)->toBeTrue()
        ->and($appreciation->user_id)->toBe($teacher->id);
});

it('toggles an appreciation between draft and final', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    Appreciation::factory()->for($teacher)->for($student)->for($class, 'schoolClass')->for($term)->create(['is_draft' => true]);

    $this->actingAs($teacher);

    Livewire::test(Appreciations::class)
        ->set('schoolClassId', $class->id)
        ->set('termId', $term->id)
        ->call('toggleDraft', $student->id);

    expect(Appreciation::query()->where('student_id', $student->id)->first()->is_draft)->toBeFalse();
});

it('suggests an appreciation based on the student\'s grades', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $evaluation = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create(['max_score' => 20]);
    Grade::factory()->for($teacher)->for($evaluation)->for($student)->create(['score' => 18, 'status' => 'graded']);

    $this->actingAs($teacher);

    Livewire::test(Appreciations::class)
        ->set('schoolClassId', $class->id)
        ->set('termId', $term->id)
        ->call('suggest', $student->id);

    $appreciation = Appreciation::query()->where('student_id', $student->id)->first();

    expect($appreciation)->not->toBeNull()
        ->and($appreciation->content)->toContain('Excellent trimestre');
});

it('applies a template to an appreciation', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $template = AppreciationTemplate::factory()->for($teacher)->create(['content' => 'Élève sérieux et impliqué.']);

    $this->actingAs($teacher);

    Livewire::test(Appreciations::class)
        ->set('schoolClassId', $class->id)
        ->set('termId', $term->id)
        ->call('applyTemplate', $student->id, (string) $template->id);

    $appreciation = Appreciation::query()->where('student_id', $student->id)->first();

    expect($appreciation->content)->toBe('Élève sérieux et impliqué.');
});

it('keeps appreciations separate per subject for a class shared between subjects', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $maths = Subject::factory()->for($teacher)->create(['name' => 'Mathématiques']);
    $informatique = Subject::factory()->for($teacher)->create(['name' => 'Informatique']);
    $class->subjects()->attach([$maths->id, $informatique->id]);
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    Livewire::test(Appreciations::class)
        ->set('schoolClassId', $class->id)
        ->set('termId', $term->id)
        ->set('subjectId', $maths->id)
        ->call('updateContent', $student->id, 'Très bon niveau en mathématiques.');

    Livewire::test(Appreciations::class)
        ->set('schoolClassId', $class->id)
        ->set('termId', $term->id)
        ->set('subjectId', $informatique->id)
        ->call('updateContent', $student->id, 'Progresse bien en informatique.');

    $mathsAppreciation = Appreciation::query()->where('student_id', $student->id)->where('subject_id', $maths->id)->first();
    $infoAppreciation = Appreciation::query()->where('student_id', $student->id)->where('subject_id', $informatique->id)->first();

    expect($mathsAppreciation->content)->toBe('Très bon niveau en mathématiques.')
        ->and($infoAppreciation->content)->toBe('Progresse bien en informatique.');
});

it("only lists a teacher's own appreciation templates", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();

    $ownTemplate = AppreciationTemplate::factory()->for($teacher)->create();
    AppreciationTemplate::factory()->for($otherTeacher)->create();

    $this->actingAs($teacher);

    Livewire::test(ListAppreciationTemplates::class)
        ->assertCanSeeTableRecords([$ownTemplate])
        ->assertCountTableRecords(1);
});

it("prevents a teacher from updating or deleting another teacher's appreciation", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    $otherTerm = Term::factory()->for($otherTeacher)->create();
    $otherStudent = Student::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();
    $appreciation = Appreciation::factory()->for($otherTeacher)->for($otherStudent)->for($otherClass, 'schoolClass')->for($otherTerm)->create();

    expect($teacher->can('update', $appreciation))->toBeFalse()
        ->and($teacher->can('delete', $appreciation))->toBeFalse();
});

it("keeps a teacher's appreciations isolated from another teacher's students", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    $otherTerm = Term::factory()->for($otherTeacher)->create();
    $otherStudent = Student::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();
    Appreciation::factory()->for($otherTeacher)->for($otherStudent)->for($otherClass, 'schoolClass')->for($otherTerm)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(Appreciations::class)
        ->set('schoolClassId', $class->id)
        ->set('termId', $term->id);

    $students = $component->get('students');

    expect($students)->toHaveCount(1)
        ->and($students->first()->id)->toBe($student->id);
});
