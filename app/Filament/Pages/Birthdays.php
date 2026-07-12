<?php

namespace App\Filament\Pages;

use App\Models\Student;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Birthdays extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCake;

    protected static ?string $navigationLabel = 'Anniversaires';

    protected static ?string $title = 'Anniversaires';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.birthdays';

    public string $period = 'week';

    public bool $showArchived = false;

    public function setPeriod(string $period): void
    {
        $this->period = $period;
    }

    /**
     * @return Collection<int, Student>
     */
    public function getStudents(): Collection
    {
        $today = Carbon::today();

        $rangeEnd = match ($this->period) {
            'day' => $today->copy(),
            'month' => $today->copy()->addDays(29),
            default => $today->copy()->addDays(6),
        };

        return Student::query()
            ->whereNotNull('birth_date')
            ->when(! $this->showArchived, fn ($query) => $query->where('is_archived', false))
            ->with('schoolClass')
            ->get()
            ->map(function (Student $student) use ($today) {
                $next = $student->birth_date->copy()->setYear($today->year);

                if ($next->lt($today)) {
                    $next = $next->addYear();
                }

                $student->next_birthday = $next;

                return $student;
            })
            ->filter(fn (Student $student) => $student->next_birthday->between($today, $rangeEnd))
            ->sortBy('next_birthday')
            ->values();
    }

    public function describeNextBirthday(Carbon $nextBirthday): string
    {
        $days = Carbon::today()->diffInDays($nextBirthday, false);

        return match (true) {
            $days === 0 => 'Aujourd\'hui',
            $days === 1 => 'Demain',
            default => 'Dans '.$days.' jours',
        };
    }
}
