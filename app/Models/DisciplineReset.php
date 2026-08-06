<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeacher;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (student, category, school class) marking the boundary the
 * "since last reset" trip counter counts from — like resetting a car's trip
 * meter, the lifetime total in DisciplineEntry is never touched. The
 * boundary is the id of the last DisciplineEntry that existed at reset
 * time (an exact, monotonic watermark), not a timestamp — see the
 * migration for why. Scoped per class so the same student's counters stay
 * independent between their real class and any groupe classe they're also
 * in.
 */
#[Fillable(['student_id', 'school_class_id', 'category', 'last_entry_id'])]
class DisciplineReset extends Model
{
    use BelongsToTeacher, HasFactory;

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }
}
