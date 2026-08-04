<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['calendar_event_id', 'path', 'original_filename'])]
class CalendarEventAttachment extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (CalendarEventAttachment $attachment) {
            Storage::disk('public')->delete($attachment->path);
        });
    }

    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class);
    }
}
