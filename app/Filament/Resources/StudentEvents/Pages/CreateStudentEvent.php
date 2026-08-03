<?php

namespace App\Filament\Resources\StudentEvents\Pages;

use App\Filament\Resources\StudentEvents\StudentEventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStudentEvent extends CreateRecord
{
    protected static string $resource = StudentEventResource::class;
}
