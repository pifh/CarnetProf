<?php

use App\Filament\Pages\ImportStudents;
use App\Models\Guardian;
use App\Models\Import;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentImporter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

function sampleStudentsCsv(): string
{
    return implode("\n", [
        'Nom,Prenom,Sexe,Naissance,Classe,Groupes',
        'Dupont,Camille,Fille,15/03/2013,6e A,Groupe rouge',
        'Martin,Lucas,Garcon,22/07/2013,6e A,',
        'Petit,Emma,Fille,01/01/2013,6e B,',
    ]);
}

it('parses a csv file into headers and rows', function () {
    $path = tempnam(sys_get_temp_dir(), 'csv').'.csv';
    file_put_contents($path, sampleStudentsCsv());

    $result = app(StudentImporter::class)->parseFile($path);

    expect($result['headers'])->toBe(['Nom', 'Prenom', 'Sexe', 'Naissance', 'Classe', 'Groupes'])
        ->and($result['rows'])->toHaveCount(3);

    unlink($path);
});

it('detects duplicates and unresolved classes during preview', function () {
    $teacher = User::factory()->create();
    $classA = SchoolClass::factory()->for($teacher)->create(['name' => '6e A']);
    Student::factory()->for($teacher)->for($classA, 'schoolClass')->create([
        'first_name' => 'Camille',
        'last_name' => 'Dupont',
    ]);

    $rows = [
        ['Dupont', 'Camille', 'Fille', '15/03/2013', '6e A'],
        ['Martin', 'Lucas', 'Garcon', '22/07/2013', '6e A'],
        ['Petit', 'Emma', 'Fille', '01/01/2013', 'Inconnue'],
    ];

    $mapping = ['last_name' => 0, 'first_name' => 1, 'sex' => 2, 'birth_date' => 3, 'class_name' => 4];

    $preview = app(StudentImporter::class)->preview($teacher, $rows, $mapping, null);

    expect($preview[0]['is_duplicate'])->toBeTrue()
        ->and($preview[1]['is_duplicate'])->toBeFalse()
        ->and($preview[1]['errors'])->toBeEmpty()
        ->and($preview[2]['errors'])->not->toBeEmpty();
});

it('executes an import, creates students and subgroups, and can be cancelled', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create(['name' => '6e A']);

    $rows = [
        ['Dupont', 'Camille', 'Fille', '15/03/2013', '6e A', 'Groupe rouge'],
    ];
    $mapping = [
        'last_name' => 0, 'first_name' => 1, 'sex' => 2,
        'birth_date' => 3, 'class_name' => 4, 'subgroup_names' => 5,
    ];

    $service = app(StudentImporter::class);
    $preview = $service->preview($teacher, $rows, $mapping, $class->id);
    $import = $service->execute($teacher, 'eleves.csv', $mapping, $class->id, $preview);

    expect($import->imported_rows)->toBe(1)
        ->and(Student::query()->where('user_id', $teacher->id)->count())->toBe(1);

    $student = Student::query()->where('user_id', $teacher->id)->first();
    expect($student->subgroups()->where('name', 'Groupe rouge')->exists())->toBeTrue();

    $service->cancel($import);

    expect($import->refresh()->status)->toBe('cancelled')
        ->and(Student::query()->where('user_id', $teacher->id)->count())->toBe(0);
});

it("updates an existing student's provided fields instead of skipping it, without blanking fields the file doesn't provide, and keeps it on cancel", function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create(['name' => '6e A']);
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create([
        'first_name' => 'Camille',
        'last_name' => 'Dupont',
        'address' => '4 rue des Lilas',
        'phone' => null,
        'email' => null,
    ]);

    // No "Adresse" column in this file at all — its existing value must survive.
    $rows = [
        ['Dupont', 'Camille', '06 12 34 56 78', 'camille.dupont@example.test'],
    ];
    $mapping = ['last_name' => 0, 'first_name' => 1, 'phone' => 2, 'email' => 3];

    $service = app(StudentImporter::class);
    $preview = $service->preview($teacher, $rows, $mapping, $class->id);
    $import = $service->execute($teacher, 'eleves.csv', $mapping, $class->id, $preview);

    expect($import->imported_rows)->toBe(0)
        ->and($import->duplicate_rows)->toBe(1)
        ->and(Student::query()->where('user_id', $teacher->id)->count())->toBe(1);

    $student->refresh();
    expect($student->phone)->toBe('06 12 34 56 78')
        ->and($student->email)->toBe('camille.dupont@example.test')
        ->and($student->address)->toBe('4 rue des Lilas');

    $importRow = $import->rows()->sole();
    expect($importRow->status)->toBe('duplicate')
        ->and($importRow->student_id)->toBe($student->id);

    $service->cancel($import);

    expect($import->refresh()->status)->toBe('cancelled')
        ->and(Student::query()->where('id', $student->id)->exists())->toBeTrue();

    $student->refresh();
    expect($student->phone)->toBe('06 12 34 56 78');
});

it('runs the full import wizard end to end', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create(['name' => '6e A']);
    SchoolClass::factory()->for($teacher)->create(['name' => '6e B']);

    $this->actingAs($teacher);

    $file = UploadedFile::fake()->createWithContent('eleves.csv', sampleStudentsCsv());

    $component = Livewire::test(ImportStudents::class)
        ->set('data.school_class_id', $class->id)
        ->set('data.file', $file);

    expect($component->get('headers'))->toBe(['Nom', 'Prenom', 'Sexe', 'Naissance', 'Classe', 'Groupes']);

    $component
        ->set('data.mapping.0', 'last_name')
        ->set('data.mapping.1', 'first_name')
        ->set('data.mapping.2', 'sex')
        ->set('data.mapping.3', 'birth_date')
        ->set('data.mapping.4', 'class_name')
        ->set('data.mapping.5', 'subgroup_names')
        ->call('analyze');

    expect($component->get('phase'))->toBe('preview')
        ->and($component->get('previewRows'))->toHaveCount(3);

    $component->call('confirmImport');

    expect($component->get('phase'))->toBe('report');

    $import = $component->get('completedImport');
    expect($import->imported_rows)->toBe(3)
        ->and(Student::query()->where('user_id', $teacher->id)->count())->toBe(3);
});

it('splits a combined "NOM Prénom" value using capitalisation as the boundary', function () {
    $service = app(StudentImporter::class);

    expect($service->splitFullName('ADMENT ROBINEAU Alexandre'))->toBe(['last_name' => 'ADMENT ROBINEAU', 'first_name' => 'Alexandre'])
        ->and($service->splitFullName('AUGE Maxence'))->toBe(['last_name' => 'AUGE', 'first_name' => 'Maxence'])
        ->and($service->splitFullName('DA CUNHA Charlotte'))->toBe(['last_name' => 'DA CUNHA', 'first_name' => 'Charlotte']);
});

it('imports students from a combined full-name column and creates both guardians', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create(['name' => '6ème 2']);

    $rows = [
        [
            'ADMENT ROBINEAU Alexandre', '02/05/2014', 'M', '6ème 2',
            'ADMENT Elsa', '07 61 07 68 35', 'lza.eamt17@gmail.com', "4 allée du Petit Champ\n78210 - SAINT CYR L'ECOLE",
            'ROBINEAU Hervé', '06 84 52 38 44', 'robihe@laposte.net', "4 allée du Petit Champ\n78210 - SAINT CYR L'ECOLE",
        ],
    ];

    $mapping = [
        'full_name' => 0, 'birth_date' => 1, 'sex' => 2, 'class_name' => 3,
        'guardian1_full_name' => 4, 'guardian1_phone' => 5, 'guardian1_email' => 6, 'guardian1_address' => 7,
        'guardian2_full_name' => 8, 'guardian2_phone' => 9, 'guardian2_email' => 10, 'guardian2_address' => 11,
    ];

    $service = app(StudentImporter::class);
    $preview = $service->preview($teacher, $rows, $mapping, $class->id);

    expect($preview[0]['errors'])->toBeEmpty()
        ->and($preview[0]['data']['last_name'])->toBe('ADMENT ROBINEAU')
        ->and($preview[0]['data']['first_name'])->toBe('Alexandre');

    $import = $service->execute($teacher, 'classe.xlsx', $mapping, $class->id, $preview);

    expect($import->imported_rows)->toBe(1);

    $student = Student::query()->where('user_id', $teacher->id)->first();
    expect($student->last_name)->toBe('ADMENT ROBINEAU')
        ->and($student->first_name)->toBe('Alexandre')
        ->and($student->guardians)->toHaveCount(2);

    $mother = Guardian::query()->where('user_id', $teacher->id)->where('last_name', 'ADMENT')->first();
    $father = Guardian::query()->where('user_id', $teacher->id)->where('last_name', 'ROBINEAU')->first();

    expect($mother->first_name)->toBe('Elsa')
        ->and($mother->email)->toBe('lza.eamt17@gmail.com')
        ->and($student->guardians()->where('guardian_id', $mother->id)->first()->pivot->is_primary)->toBeTrue()
        ->and($father->first_name)->toBe('Hervé')
        ->and($student->guardians()->where('guardian_id', $father->id)->first()->pivot->is_primary)->toBeFalse();
});

it("prevents a teacher from cancelling another teacher's import", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $import = Import::factory()->for($otherTeacher)->create();

    $this->actingAs($teacher);

    expect(fn () => Livewire::test(ImportStudents::class)->call('cancelPastImport', $import->id))
        ->toThrow(ModelNotFoundException::class);

    expect($import->refresh()->status)->toBe('completed');
});
