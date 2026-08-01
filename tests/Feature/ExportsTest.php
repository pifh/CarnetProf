<?php

use App\Filament\Pages\Averages;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Models\Appreciation;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\BulletinGenerator;
use Livewire\Livewire;

it('builds bulletin data with only published appreciations', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $evaluation = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create(['title' => 'Contrôle 1', 'max_score' => 20]);
    Grade::factory()->for($teacher)->for($evaluation)->for($student)->create(['score' => 15, 'status' => 'graded']);

    Appreciation::factory()->for($teacher)->for($student)->for($class, 'schoolClass')->for($term)->create([
        'type' => 'general',
        'content' => 'Bon trimestre.',
        'is_draft' => false,
    ]);
    Appreciation::factory()->for($teacher)->for($student)->for($class, 'schoolClass')->for($term)->create([
        'type' => 'disciplinary',
        'content' => 'Brouillon non publié.',
        'is_draft' => true,
    ]);

    $bulletin = app(BulletinGenerator::class)->build($student, $class, $term);

    expect($bulletin['average'])->toBe(15.0)
        ->and($bulletin['evaluations'])->toHaveCount(1)
        ->and($bulletin['evaluations']->first()['title'])->toBe('Contrôle 1')
        ->and($bulletin['generalAppreciation'])->toBe('Bon trimestre.')
        ->and($bulletin['disciplinaryAppreciation'])->toBeNull();
});

it('builds bulletin data scoped to a single subject when the class has several', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $maths = Subject::factory()->for($teacher)->create(['name' => 'Mathématiques']);
    $informatique = Subject::factory()->for($teacher)->create(['name' => 'Informatique']);
    $class->subjects()->attach([$maths->id, $informatique->id]);
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $mathsEval = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->for($maths)->create(['title' => 'Contrôle maths', 'max_score' => 20]);
    Grade::factory()->for($teacher)->for($mathsEval)->for($student)->create(['score' => 15, 'status' => 'graded']);

    $infoEval = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->for($informatique)->create(['title' => 'Contrôle info', 'max_score' => 20]);
    Grade::factory()->for($teacher)->for($infoEval)->for($student)->create(['score' => 5, 'status' => 'graded']);

    Appreciation::factory()->for($teacher)->for($student)->for($class, 'schoolClass')->for($term)->for($maths)->create([
        'type' => 'general', 'content' => 'Excellent en maths.', 'is_draft' => false,
    ]);

    $bulletin = app(BulletinGenerator::class)->build($student, $class, $term, $maths);

    expect($bulletin['average'])->toBe(15.0)
        ->and($bulletin['evaluations'])->toHaveCount(1)
        ->and($bulletin['evaluations']->first()['title'])->toBe('Contrôle maths')
        ->and($bulletin['generalAppreciation'])->toBe('Excellent en maths.');
});

it('downloads a single student bulletin as a PDF', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    Livewire::test(Averages::class)
        ->set('schoolClassId', $class->id)
        ->set('termId', $term->id)
        ->call('downloadBulletin', $student->id)
        ->assertFileDownloaded(contentType: 'application/pdf');
});

it("refuses to download a bulletin for another teacher's student", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();

    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    $otherStudent = Student::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();

    $this->actingAs($teacher);

    Livewire::test(Averages::class)
        ->set('schoolClassId', $class->id)
        ->set('termId', $term->id)
        ->call('downloadBulletin', $otherStudent->id)
        ->assertNoFileDownloaded();
});

it('downloads a combined PDF of bulletins for the whole class', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->count(2)->create();

    $this->actingAs($teacher);

    Livewire::test(Averages::class)
        ->set('schoolClassId', $class->id)
        ->set('termId', $term->id)
        ->call('downloadClassBulletins')
        ->assertFileDownloaded(contentType: 'application/pdf');
});

it('exports the averages table as a CSV file', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create(['last_name' => 'Aaa', 'first_name' => 'Bob']);
    $evaluation = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create(['max_score' => 20]);
    Grade::factory()->for($teacher)->for($evaluation)->for($student)->create(['score' => 10, 'status' => 'graded']);

    $this->actingAs($teacher);

    $expectedCsv = "\xEF\xBB\xBFNom,Prénom,Moyenne\nAaa,Bob,10.00\n";

    Livewire::test(Averages::class)
        ->set('schoolClassId', $class->id)
        ->set('termId', $term->id)
        ->call('exportCsv')
        ->assertFileDownloaded(content: $expectedCsv, contentType: 'text/csv');
});

it("only exports a teacher's own students to CSV", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->create(['last_name' => 'Own', 'first_name' => 'Student', 'birth_date' => null]);

    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    Student::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create(['last_name' => 'Other', 'first_name' => 'Student']);

    $this->actingAs($teacher);

    $component = Livewire::test(ListStudents::class)->callAction('export');
    $downloadedContent = base64_decode(data_get($component->effects, 'download.content'));

    expect($downloadedContent)->toContain('Own')
        ->and($downloadedContent)->not->toContain('Other');
});
