<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeacher;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

#[Fillable(['school_year', 'label', 'starts_on', 'ends_on', 'position', 'parent_id'])]
class Term extends Model
{
    use BelongsToTeacher, HasFactory;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Term::class, 'parent_id');
    }

    /**
     * The term ids to include when aggregating grades "for this term": itself,
     * plus any sub-periods it contains (a period's own grades already belong
     * to its parent trimester). A period (which has no children) simply
     * resolves to itself.
     *
     * @return array<int, int>
     */
    public function aggregationTermIds(): array
    {
        return collect([$this->id])->merge($this->children()->pluck('id'))->all();
    }

    /**
     * Flat, display-ordered list for select inputs: every top-level term
     * (trimestre) immediately followed by its own sub-periods.
     *
     * @return Collection<int, Term>
     */
    public static function hierarchicalForTeacher(): Collection
    {
        $all = static::query()->where('user_id', Auth::id())->orderBy('school_year')->orderBy('position')->get();

        return $all->whereNull('parent_id')
            ->flatMap(fn (Term $term) => collect([$term])->merge($all->where('parent_id', $term->id)))
            ->values();
    }

    public static function currentSchoolYear(): string
    {
        return SchoolClass::currentSchoolYear();
    }
}
