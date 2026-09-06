<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeacher;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

#[Fillable([
    'name', 'level', 'school_year', 'color', 'notes', 'is_archived', 'archived_at',
    'seating_pdf_first_name_font_size', 'seating_pdf_last_name_font_size',
])]
class SchoolClass extends Model
{
    use BelongsToTeacher, HasFactory, SoftDeletes;

    public const DEFAULT_SEATING_PDF_FIRST_NAME_FONT_SIZE = 14;

    public const DEFAULT_SEATING_PDF_LAST_NAME_FONT_SIZE = 8;

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * Seating-chart PDF font sizes are saved per class (not per plan or per
     * application) — every plan printed for this class shares the same
     * choice, until the teacher changes it again.
     */
    public function seatingPdfFirstNameFontSizeOrDefault(): int
    {
        return $this->seating_pdf_first_name_font_size ?? self::DEFAULT_SEATING_PDF_FIRST_NAME_FONT_SIZE;
    }

    public function seatingPdfLastNameFontSizeOrDefault(): int
    {
        return $this->seating_pdf_last_name_font_size ?? self::DEFAULT_SEATING_PDF_LAST_NAME_FONT_SIZE;
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class);
    }

    /**
     * Classes kept with no subject attached are pure archives (old cohorts kept
     * only so their students remain visible in the Trombinoscope and for
     * birthdays) — exclude them everywhere else a "which class am I working
     * in" selector is built.
     */
    public function scopeHasSubjects(Builder $query): Builder
    {
        return $query->has('subjects');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function groupClassMembers(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'school_class_student');
    }

    /**
     * Every student attending this class: those for whom it's their real/primary
     * class, plus those attached as secondary "groupe classe" members (e.g. an
     * NSI group pulling students from several different real classes). Returns
     * a Builder so it's a drop-in replacement for students() wherever teaching
     * features (grades, cahier de texte, discipline...) list "this class's kids".
     */
    public function allStudents(): Builder
    {
        return Student::query()->where(
            fn (Builder $query) => $query
                ->where('school_class_id', $this->id)
                ->orWhereHas('groupClasses', fn (Builder $q) => $q->whereKey($this->id))
        );
    }

    public function subgroups(): HasMany
    {
        return $this->hasMany(StudentSubgroup::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    public function seatingPlanApplications(): HasMany
    {
        return $this->hasMany(SeatingPlanApplication::class);
    }

    public function progressionSequences(): HasMany
    {
        return $this->hasMany(ProgressionSequence::class);
    }

    public function logbookEntries(): HasMany
    {
        return $this->hasMany(LogbookEntry::class);
    }

    public function groupGenerations(): HasMany
    {
        return $this->hasMany(GroupGeneration::class);
    }

    public static function currentSchoolYear(): string
    {
        $now = Carbon::now();
        $startYear = $now->month >= 7 ? $now->year : $now->year - 1;

        return $startYear.'-'.($startYear + 1);
    }
}
