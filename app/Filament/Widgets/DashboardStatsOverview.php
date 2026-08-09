<?php

namespace App\Filament\Widgets;

use App\Models\SchoolClass;
use App\Models\Student;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class DashboardStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $today = Carbon::today();

        $activeClasses = SchoolClass::query()->where('is_archived', false)->hasSubjects()->count();
        $activeStudents = Student::query()->where('is_archived', false)->count();
        $birthdaysToday = Student::query()
            ->where('is_archived', false)
            ->whereNotNull('birth_date')
            ->whereMonth('birth_date', $today->month)
            ->whereDay('birth_date', $today->day)
            ->count();

        return [
            Stat::make('Classes actives', $activeClasses),
            Stat::make('Élèves actifs', $activeStudents),
            Stat::make('Anniversaires aujourd\'hui', $birthdaysToday)
                ->color($birthdaysToday > 0 ? 'success' : 'gray'),
        ];
    }
}
