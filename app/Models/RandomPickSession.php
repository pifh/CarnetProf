<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeacher;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['school_class_id', 'subject_id', 'name'])]
class RandomPickSession extends Model
{
    use BelongsToTeacher, HasFactory;

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function picks(): HasMany
    {
        return $this->hasMany(RandomPick::class);
    }
}
