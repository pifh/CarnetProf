<?php

namespace App\Models;

use Database\Factories\BackupDestinationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A remote storage destination backups can be pushed to. `user_id === null`
 * is a deliberate, permanent state meaning "site-wide" (managed by admins),
 * not a fallback like login_logs' nullable user_id. Deliberately does NOT
 * use BelongsToTeacher: that trait's creating() hook would force
 * `user_id = auth()->id()`, making a genuinely site-wide row impossible to
 * create. Ownership is instead set explicitly by whichever page creates the
 * record.
 */
#[Fillable(['user_id', 'provider', 'label', 'credentials', 'is_active', 'last_used_at'])]
class BackupDestination extends Model
{
    /** @use HasFactory<BackupDestinationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPersonal(): bool
    {
        return $this->user_id !== null;
    }

    public function isSiteWide(): bool
    {
        return $this->user_id === null;
    }

    public function diskName(): string
    {
        return ($this->isSiteWide() ? 'site_backup_' : 'personal_backup_').$this->id;
    }
}
