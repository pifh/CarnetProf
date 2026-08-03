<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['student_event_id', 'path', 'original_filename'])]
class StudentEventAttachment extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (StudentEventAttachment $attachment) {
            Storage::disk('public')->delete($attachment->path);
        });
    }

    public function studentEvent(): BelongsTo
    {
        return $this->belongsTo(StudentEvent::class);
    }
}
