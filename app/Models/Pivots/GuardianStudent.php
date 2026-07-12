<?php

namespace App\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;

class GuardianStudent extends Pivot
{
    protected $table = 'guardian_student';

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }
}
