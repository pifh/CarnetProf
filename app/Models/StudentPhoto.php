<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeacher;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['student_id', 'path', 'school_year', 'source', 'is_current'])]
class StudentPhoto extends Model
{
    use BelongsToTeacher, HasFactory;

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
