<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeacher;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['school_class_id', 'name'])]
class SeatingPlan extends Model
{
    use BelongsToTeacher, HasFactory;

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function desks(): HasMany
    {
        return $this->hasMany(SeatingPlanDesk::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(SeatingPlanSeat::class);
    }
}
