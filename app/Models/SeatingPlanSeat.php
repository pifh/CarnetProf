<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['seating_plan_id', 'seating_plan_desk_id', 'seat_index', 'student_id'])]
class SeatingPlanSeat extends Model
{
    use HasFactory;

    public function seatingPlan(): BelongsTo
    {
        return $this->belongsTo(SeatingPlan::class);
    }

    public function desk(): BelongsTo
    {
        return $this->belongsTo(SeatingPlanDesk::class, 'seating_plan_desk_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
