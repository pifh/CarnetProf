<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeacher;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_class_id', 'subject_id', 'progression_sequence_id', 'ecole_directe_event_id', 'date', 'status', 'content', 'homework'])]
class LogbookEntry extends Model
{
    use BelongsToTeacher, HasFactory;

    const STATUS_PLANNED = 'planned';

    const STATUS_DONE = 'done';

    protected function casts(): array
    {
        return [
            'date' => 'date',
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

    public function progressionSequence(): BelongsTo
    {
        return $this->belongsTo(ProgressionSequence::class);
    }

    public function ecoleDirecteEvent(): BelongsTo
    {
        return $this->belongsTo(EcoleDirecteEvent::class);
    }
}
