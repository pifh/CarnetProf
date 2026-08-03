<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeacher;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

#[Fillable(['student_id', 'type', 'event_date', 'notes'])]
class StudentEvent extends Model
{
    use BelongsToTeacher, HasFactory;

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Attachment files must be purged from disk when an event is removed,
        // which only happens if each child row goes through Eloquent's own
        // delete (the FK's cascadeOnDelete is just a DB-level backstop).
        static::deleting(function (StudentEvent $event) {
            $event->attachments->each->delete();
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(StudentEventAttachment::class);
    }

    /**
     * @return array<int, string>
     */
    public static function allTypes(): array
    {
        return static::query()
            ->where('user_id', Auth::id())
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->all();
    }
}
