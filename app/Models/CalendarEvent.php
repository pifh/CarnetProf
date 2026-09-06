<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeacher;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'title', 'notes', 'starts_at', 'ends_at', 'all_day'])]
class CalendarEvent extends Model
{
    use BelongsToTeacher, HasFactory;

    const TYPE_REUNION = 'reunion';

    const TYPE_RDV = 'rdv';

    const TYPE_ETABLISSEMENT = 'etablissement';

    const TYPE_VACANCES = 'vacances';

    const TYPE_DST = 'dst';

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Attachment files must be purged from disk when an event is removed,
        // which only happens if each child row goes through Eloquent's own
        // delete (the FK's cascadeOnDelete is just a DB-level backstop).
        static::deleting(function (CalendarEvent $event) {
            $event->attachments->each->delete();
        });
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CalendarEventAttachment::class);
    }

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_REUNION => 'Réunion',
            self::TYPE_RDV => 'Rendez-vous',
            self::TYPE_ETABLISSEMENT => 'Événement établissement',
            self::TYPE_VACANCES => 'Vacances',
            self::TYPE_DST => 'DST (devoir surveillé)',
        ];
    }
}
