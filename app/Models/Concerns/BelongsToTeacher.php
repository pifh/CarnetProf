<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the currently authenticated teacher and stamps new
 * records with their user_id, so private data can never leak between accounts.
 */
trait BelongsToTeacher
{
    public static function bootBelongsToTeacher(): void
    {
        static::addGlobalScope('teacher', function (Builder $query) {
            if (auth()->check()) {
                $query->where($query->getModel()->getTable().'.user_id', auth()->id());
            }
        });

        static::creating(function ($model) {
            if (empty($model->user_id) && auth()->check()) {
                $model->user_id = auth()->id();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
