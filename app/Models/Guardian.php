<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeacher;
use App\Models\Pivots\GuardianStudent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['first_name', 'last_name', 'relationship', 'phone', 'email', 'address'])]
class Guardian extends Model
{
    use BelongsToTeacher, HasFactory;

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)
            ->using(GuardianStudent::class)
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
