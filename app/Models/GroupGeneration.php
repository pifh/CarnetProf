<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeacher;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['school_class_id', 'subject_id', 'activity_name', 'term_id', 'evaluation_id', 'mode', 'group_count', 'group_size', 'groups', 'criteria'])]
class GroupGeneration extends Model
{
    use BelongsToTeacher, HasFactory;

    protected function casts(): array
    {
        return [
            'groups' => 'array',
            'criteria' => 'array',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function subgroups(): HasMany
    {
        return $this->hasMany(StudentSubgroup::class);
    }
}
