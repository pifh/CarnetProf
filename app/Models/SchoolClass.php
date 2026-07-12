<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeacher;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable(['subject_id', 'name', 'level', 'school_year', 'color', 'notes', 'is_archived', 'archived_at'])]
class SchoolClass extends Model
{
    use BelongsToTeacher, HasFactory;

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function subgroups(): HasMany
    {
        return $this->hasMany(StudentSubgroup::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    public static function currentSchoolYear(): string
    {
        $now = Carbon::now();
        $startYear = $now->month >= 7 ? $now->year : $now->year - 1;

        return $startYear.'-'.($startYear + 1);
    }
}
