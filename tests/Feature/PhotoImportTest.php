<?php

use App\Filament\Pages\ImportClassPhotos;
use App\Models\PhotoImport;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

function makeFixtureJpeg(string $path, int $width, int $height): void
{
    $image = imagecreatetruecolor($width, $height);
    imagejpeg($image, $path);
    imagedestroy($image);
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    Process::fake();
});

afterEach(function () {
    Str::createUuidsNormally();
});

it('extracts photos and pre-fills assignments in alphabetical roster order', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $studentA = Student::factory()->for($teacher)->for($class, 'schoolClass')->create(['last_name' => 'Aaronson']);
    $studentB = Student::factory()->for($teacher)->for($class, 'schoolClass')->create(['last_name' => 'Bertrand']);

    $uuid = Str::freezeUuids();
    Storage::disk('local')->makeDirectory("photo-imports/{$uuid}");
    makeFixtureJpeg(Storage::disk('local')->path("photo-imports/{$uuid}/img-000.jpg"), 200, 266);
    makeFixtureJpeg(Storage::disk('local')->path("photo-imports/{$uuid}/img-001.jpg"), 200, 266);

    $this->actingAs($teacher);

    $component = Livewire::test(ImportClassPhotos::class, ['schoolClass' => $class])
        ->set('pdfFile', UploadedFile::fake()->create('trombi.pdf', 50, 'application/pdf'))
        ->call('analyze');

    $component->assertSet('phase', 'review');

    $photoImport = PhotoImport::query()->where('school_class_id', $class->id)->sole();
    expect($photoImport->status)->toBe('pending');

    $photos = $photoImport->photos()->orderBy('position')->get();
    expect($photos)->toHaveCount(2);

    $assignments = $component->get('assignments');
    expect($assignments[$photos[0]->id])->toBe($studentA->id)
        ->and($assignments[$photos[1]->id])->toBe($studentB->id);
});

it('shows a clear message and creates nothing when no exploitable photos are found', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    Str::freezeUuids();

    $this->actingAs($teacher);

    $component = Livewire::test(ImportClassPhotos::class, ['schoolClass' => $class])
        ->set('pdfFile', UploadedFile::fake()->create('trombi.pdf', 50, 'application/pdf'))
        ->call('analyze');

    $component->assertSet('phase', 'upload');
    expect($component->get('analysisError'))->not->toBeNull()
        ->and(PhotoImport::query()->where('school_class_id', $class->id)->exists())->toBeFalse();
});

it('confirms assigned photos as current student photos and discards ignored ones', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $studentA = Student::factory()->for($teacher)->for($class, 'schoolClass')->create(['last_name' => 'Aaronson']);
    $studentB = Student::factory()->for($teacher)->for($class, 'schoolClass')->create(['last_name' => 'Bertrand']);

    $uuid = Str::freezeUuids();
    Storage::disk('local')->makeDirectory("photo-imports/{$uuid}");
    makeFixtureJpeg(Storage::disk('local')->path("photo-imports/{$uuid}/img-000.jpg"), 200, 266);
    makeFixtureJpeg(Storage::disk('local')->path("photo-imports/{$uuid}/img-001.jpg"), 200, 266);

    $this->actingAs($teacher);

    $component = Livewire::test(ImportClassPhotos::class, ['schoolClass' => $class])
        ->set('pdfFile', UploadedFile::fake()->create('trombi.pdf', 50, 'application/pdf'))
        ->call('analyze');

    $photoImport = PhotoImport::query()->where('school_class_id', $class->id)->sole();
    $photos = $photoImport->photos()->orderBy('position')->get();

    // Ignore the second photo instead of confirming studentB.
    $component
        ->set("assignments.{$photos[1]->id}", '')
        ->call('confirmImport')
        ->assertSet('phase', 'report');

    $photoA = StudentPhoto::query()->where('student_id', $studentA->id)->sole();
    expect($photoA->is_current)->toBeTrue()
        ->and($photoA->source)->toBe('pdf_import');
    Storage::disk('public')->assertExists($photoA->path);

    expect(StudentPhoto::query()->where('student_id', $studentB->id)->exists())->toBeFalse();

    Storage::disk('public')->assertMissing($photos[1]->path);

    expect($photoImport->fresh()->status)->toBe('completed');
});

it('cleans up files and the pending import row on restart', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $uuid = Str::freezeUuids();
    Storage::disk('local')->makeDirectory("photo-imports/{$uuid}");
    makeFixtureJpeg(Storage::disk('local')->path("photo-imports/{$uuid}/img-000.jpg"), 200, 266);

    $this->actingAs($teacher);

    $component = Livewire::test(ImportClassPhotos::class, ['schoolClass' => $class])
        ->set('pdfFile', UploadedFile::fake()->create('trombi.pdf', 50, 'application/pdf'))
        ->call('analyze');

    $photoImport = PhotoImport::query()->where('school_class_id', $class->id)->sole();
    $photo = $photoImport->photos()->sole();

    $component->call('restart')->assertSet('phase', 'upload');

    expect(PhotoImport::query()->find($photoImport->id))->toBeNull();
    Storage::disk('public')->assertMissing($photo->path);
});

it("blocks a teacher from opening another teacher's photo import wizard", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();

    $this->actingAs($teacher);

    Livewire::test(ImportClassPhotos::class, ['schoolClass' => $otherClass])
        ->assertForbidden();
});
