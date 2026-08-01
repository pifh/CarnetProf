<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeacher;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'color'])]
class Subject extends Model
{
    use BelongsToTeacher, HasFactory;

    public function schoolClasses(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class);
    }
}
