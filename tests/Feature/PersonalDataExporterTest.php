<?php

use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentEvent;
use App\Models\StudentEventAttachment;
use App\Models\StudentPhoto;
use App\Models\User;
use App\Services\PersonalDataExporter;
use Illuminate\Support\Facades\Storage;

it("bundles a teacher's own data, with nested child relations and referenced files, and excludes other teachers", function () {
    Storage::fake('public');

    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();

    $this->actingAs($teacher);

    $schoolClass = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($schoolClass, 'schoolClass')->create();
    $guardian = Guardian::factory()->for($teacher)->create();
    $student->guardians()->attach($guardian->id, ['is_primary' => true]);

    Storage::disk('public')->put('students/photo.jpg', 'fake-photo-bytes');
    StudentPhoto::factory()->for($teacher)->for($student)->create(['path' => 'students/photo.jpg']);

    $event = StudentEvent::factory()->for($teacher)->for($student)->create();
    Storage::disk('public')->put('student-events/attachment.jpg', 'fake-attachment-bytes');
    StudentEventAttachment::factory()->for($event, 'studentEvent')->create(['path' => 'student-events/attachment.jpg']);

    // Unrelated data belonging to another teacher, must never appear in the export.
    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    Student::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();

    $exporter = app(PersonalDataExporter::class);
    $ref = new ReflectionClass($exporter);
    $method = $ref->getMethod('buildZip');
    $method->setAccessible(true);

    $zipPath = $method->invoke($exporter, $teacher);

    $zip = new ZipArchive;
    $zip->open($zipPath);

    $studentsJson = json_decode($zip->getFromName('data/student.json'), true);
    expect($studentsJson)->toHaveCount(1)
        ->and($studentsJson[0]['id'])->toBe($student->id);

    $eventsJson = json_decode($zip->getFromName('data/student_event.json'), true);
    expect($eventsJson[0]['attachments'])->toHaveCount(1)
        ->and($eventsJson[0]['attachments'][0]['path'])->toBe('student-events/attachment.jpg');

    expect($zip->getFromName('files/students/photo.jpg'))->toBe('fake-photo-bytes')
        ->and($zip->getFromName('files/student-events/attachment.jpg'))->toBe('fake-attachment-bytes');

    $zip->close();
    unlink($zipPath);
});
