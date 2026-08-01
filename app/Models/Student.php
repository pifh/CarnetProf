<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeacher;
use App\Models\Pivots\GuardianStudent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'school_class_id', 'first_name', 'last_name', 'sex', 'birth_date', 'address',
    'phone', 'email', 'is_delegate', 'special_needs', 'seating_notes',
    'pedagogical_notes', 'private_notes', 'is_archived', 'archived_at',
])]
class Student extends Model
{
    use BelongsToTeacher, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_delegate' => 'boolean',
            'is_archived' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class)
            ->using(GuardianStudent::class)
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function subgroups(): BelongsToMany
    {
        return $this->belongsToMany(StudentSubgroup::class, 'student_student_subgroup');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function appreciations(): HasMany
    {
        return $this->hasMany(Appreciation::class);
    }

    public function randomPicks(): HasMany
    {
        return $this->hasMany(RandomPick::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
