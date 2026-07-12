<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeacher;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['school_class_id', 'term_id', 'title', 'exam_date', 'coefficient', 'max_score'])]
class Evaluation extends Model
{
    use BelongsToTeacher, HasFactory;

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'coefficient' => 'decimal:2',
            'max_score' => 'decimal:2',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }
}
