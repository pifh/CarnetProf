<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['seating_plan_id', 'position_row', 'position_col', 'capacity'])]
class SeatingPlanDesk extends Model
{
    use HasFactory;

    public function seatingPlan(): BelongsTo
    {
        return $this->belongsTo(SeatingPlan::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(SeatingPlanSeat::class);
    }
}
