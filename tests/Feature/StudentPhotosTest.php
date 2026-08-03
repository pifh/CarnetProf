<?php

use App\Filament\Pages\Trombinoscope;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentPhoto;
use App\Models\User;
use App\Services\StudentPhotoManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('shows no photo url until one is uploaded', function () {
    $student = Student::factory()->create();

    expect($student->photo_url)->toBeNull();
});

it('uploads a photo from the trombinoscope and marks it current', function () {
    Storage::fake('public');

    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create(['school_year' => '2026-2027']);
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    Livewire::test(Trombinoscope::class)
        ->set('schoolClassId', $class->id)
        ->call('toggleManaging', $student->id)
        ->set('newPhoto', UploadedFile::fake()->image('photo.jpg'))
        ->call('uploadPhoto');

    $photo = StudentPhoto::query()->where('student_id', $student->id)->sole();

    expect($photo->is_current)->toBeTrue()
        ->and($photo->school_year)->toBe('2026-2027')
        ->and($photo->source)->toBe('manual')
        ->and($photo->user_id)->toBe($teacher->id);

    Storage::disk('public')->assertExists($photo->path);
    expect($student->fresh()->photo_url)->toBe(Storage::disk('public')->url($photo->path));
});

it('archives the previous photo instead of deleting it when a new one is uploaded', function () {
    Storage::fake('public');

    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(Trombinoscope::class)
        ->set('schoolClassId', $class->id)
        ->call('toggleManaging', $student->id)
        ->set('newPhoto', UploadedFile::fake()->image('first.jpg'))
        ->call('uploadPhoto');

    $firstPhoto = StudentPhoto::query()->where('student_id', $student->id)->sole();

    $component
        ->set('newPhoto', UploadedFile::fake()->image('second.jpg'))
        ->call('uploadPhoto');

    $firstPhoto->refresh();
    $secondPhoto = StudentPhoto::query()->where('student_id', $student->id)->where('is_current', true)->sole();

    expect($firstPhoto->is_current)->toBeFalse()
        ->and($secondPhoto->id)->not->toBe($firstPhoto->id)
        ->and(StudentPhoto::query()->where('student_id', $student->id)->count())->toBe(2);

    Storage::disk('public')->assertExists($firstPhoto->path);
    Storage::disk('public')->assertExists($secondPhoto->path);
});

it('restores an archived photo back to current', function () {
    Storage::fake('public');

    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $oldPhoto = StudentPhoto::factory()->for($teacher)->for($student)->archived()->create();
    $currentPhoto = StudentPhoto::factory()->for($teacher)->for($student)->create();

    $this->actingAs($teacher);

    Livewire::test(Trombinoscope::class)
        ->set('schoolClassId', $class->id)
        ->call('toggleManaging', $student->id)
        ->call('restorePhoto', $oldPhoto->id);

    expect($oldPhoto->fresh()->is_current)->toBeTrue()
        ->and($currentPhoto->fresh()->is_current)->toBeFalse();
});

it('refuses to permanently delete the current photo', function () {
    Storage::fake('public');

    $teacher = User::factory()->create();
    $photo = StudentPhoto::factory()->for($teacher)->create(['path' => 'students/current.jpg']);
    Storage::disk('public')->put($photo->path, 'fake-image-content');

    app(StudentPhotoManager::class)->deletePermanently($photo);

    expect(StudentPhoto::query()->find($photo->id))->not->toBeNull();
    Storage::disk('public')->assertExists($photo->path);
});

it('permanently deletes an archived photo and its file', function () {
    Storage::fake('public');

    $teacher = User::factory()->create();
    $photo = StudentPhoto::factory()->for($teacher)->archived()->create(['path' => 'students/old.jpg']);
    Storage::disk('public')->put($photo->path, 'fake-image-content');

    app(StudentPhotoManager::class)->deletePermanently($photo);

    expect(StudentPhoto::query()->find($photo->id))->toBeNull();
    Storage::disk('public')->assertMissing($photo->path);
});

it("keeps a teacher's photo history isolated from another teacher's students", function () {
    Storage::fake('public');

    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();

    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    $otherStudent = Student::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();
    $otherPhoto = StudentPhoto::factory()->for($otherTeacher)->for($otherStudent)->archived()->create();

    $class = SchoolClass::factory()->for($teacher)->create();

    $this->actingAs($teacher);

    Livewire::test(Trombinoscope::class)
        ->set('schoolClassId', $class->id)
        ->call('restorePhoto', $otherPhoto->id);

    expect($otherPhoto->fresh()->is_current)->toBeFalse();
});
