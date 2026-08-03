<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['photo_import_id', 'position', 'path', 'student_id'])]
class PhotoImportPhoto extends Model
{
    public function photoImport(): BelongsTo
    {
        return $this->belongsTo(PhotoImport::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
