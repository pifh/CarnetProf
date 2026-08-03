<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['seating_plan_application_id', 'seating_plan_desk_id', 'seat_index', 'student_id', 'is_locked', 'is_blocked'])]
class SeatingPlanSeat extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
            'is_blocked' => 'boolean',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(SeatingPlanApplication::class, 'seating_plan_application_id');
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
