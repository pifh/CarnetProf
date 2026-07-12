<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeacher;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['school_year', 'label', 'position'])]
class Term extends Model
{
    use BelongsToTeacher, HasFactory;

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    public static function currentSchoolYear(): string
    {
        return SchoolClass::currentSchoolYear();
    }
}
