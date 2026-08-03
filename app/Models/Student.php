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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'school_class_id', 'first_name', 'last_name', 'sex', 'birth_date', 'address',
    'phone', 'email', 'is_delegate', 'special_needs', 'seating_notes',
    'seating_row_preference', 'seating_allowed_columns',
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
            'special_needs' => 'array',
            'seating_allowed_columns' => 'array',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function allSpecialNeedsTags(): array
    {
        return static::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('special_needs')
            ->pluck('special_needs')
            ->flatten()
            ->unique()
            ->sort()
            ->values()
            ->all();
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

    public function seatingNextTo(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_seating_pairs', 'student_id', 'related_student_id')
            ->wherePivot('type', 'next_to')
            ->withPivotValue('type', 'next_to')
            ->withTimestamps();
    }

    public function seatingNotNextTo(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_seating_pairs', 'student_id', 'related_student_id')
            ->wherePivot('type', 'not_next_to')
            ->withPivotValue('type', 'not_next_to')
            ->withTimestamps();
    }

    public function seatingFarFrom(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_seating_pairs', 'student_id', 'related_student_id')
            ->wherePivot('type', 'far_from')
            ->withPivotValue('type', 'far_from')
            ->withTimestamps();
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

    public function photos(): HasMany
    {
        return $this->hasMany(StudentPhoto::class)->orderByDesc('created_at');
    }

    public function events(): HasMany
    {
        return $this->hasMany(StudentEvent::class)->orderByDesc('event_date');
    }

    public function currentPhoto(): HasOne
    {
        return $this->hasOne(StudentPhoto::class)->where('is_current', true);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->currentPhoto ? Storage::disk('public')->url($this->currentPhoto->path) : null;
    }
}
