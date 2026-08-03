<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeacher;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable room layout — desks, capacities, teacher's desk position — with
 * no class of its own, since the whole point is that it can be applied to
 * several different classes (each via its own SeatingPlanApplication).
 */
#[Fillable(['name', 'teacher_desk_position'])]
class SeatingPlan extends Model
{
    use BelongsToTeacher, HasFactory;

    public function desks(): HasMany
    {
        return $this->hasMany(SeatingPlanDesk::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(SeatingPlanApplication::class);
    }
}
