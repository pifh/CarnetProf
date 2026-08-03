<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentPhoto;
use Illuminate\Support\Facades\Storage;

/**
 * Centralizes the "archive, never overwrite" rule for student photos: setting a
 * new photo demotes the previous one instead of deleting it, so past photos stay
 * available (manual upload and PDF import both go through this).
 */
class StudentPhotoManager
{
    public function setPhoto(Student $student, string $path, string $source = 'manual'): StudentPhoto
    {
        StudentPhoto::query()
            ->where('student_id', $student->id)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        $photo = new StudentPhoto([
            'student_id' => $student->id,
            'path' => $path,
            'school_year' => $student->schoolClass?->school_year,
            'source' => $source,
            'is_current' => true,
        ]);
        $photo->user_id = $student->user_id;
        $photo->save();

        return $photo;
    }

    public function restore(StudentPhoto $photo): void
    {
        StudentPhoto::query()
            ->where('student_id', $photo->student_id)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        $photo->update(['is_current' => true]);
    }

    /**
     * Permanently deletes an archived photo's file and row — never the current
     * one, which must be replaced or restored-over instead. Teacher-initiated
     * only, e.g. to honour an erasure request; nothing calls this automatically.
     */
    public function deletePermanently(StudentPhoto $photo): void
    {
        if ($photo->is_current) {
            return;
        }

        Storage::disk('public')->delete($photo->path);
        $photo->delete();
    }
}
