<?php

namespace App\Filament\Pages;

use App\DataTransferObjects\CalendarItem;
use App\Services\CalendarItemCollector;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

class Calendrier extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Calendrier';

    protected static ?string $title = 'Calendrier';

    protected static ?int $navigationSort = 29;

    protected string $view = 'filament.pages.calendrier';

    /** Pixel height of one hour row in the day/week timeline grid. */
    private const HOUR_HEIGHT_PX = 48;

    /** @var 'day'|'week'|'month' */
    #[Url]
    public string $mode = 'month';

    /** Y-m-d of the day/week/month currently in view. */
    #[Url]
    public ?string $cursor = null;

    public function mount(): void
    {
        if (! $this->cursor) {
            $this->cursor = now()->format('Y-m-d');
        }
    }

    public function setMode(string $mode): void
    {
        $this->mode = in_array($mode, ['day', 'week', 'month'], true) ? $mode : 'month';
    }

    public function previous(): void
    {
        $date = $this->cursorDate();
        $this->cursor = (match ($this->mode) {
            'day' => $date->subDay(),
            'week' => $date->subWeek(),
            default => $date->subMonth(),
        })->format('Y-m-d');
    }

    public function next(): void
    {
        $date = $this->cursorDate();
        $this->cursor = (match ($this->mode) {
            'day' => $date->addDay(),
            'week' => $date->addWeek(),
            default => $date->addMonth(),
        })->format('Y-m-d');
    }

    public function today(): void
    {
        $this->cursor = now()->format('Y-m-d');
    }

    public function goToDay(string $date): void
    {
        $this->cursor = $date;
        $this->mode = 'day';
    }

    public function getHeadingLabelProperty(): string
    {
        $date = $this->cursorDate();

        return match ($this->mode) {
            'day' => ucfirst($date->translatedFormat('l j F Y')),
            'week' => 'Semaine du '.$this->weekStart()->translatedFormat('j F').' au '.$this->weekEnd()->translatedFormat('j F Y'),
            default => ucfirst($date->translatedFormat('F Y')),
        };
    }

    /**
     * @return array<int, Carbon>
     */
    public function getMonthGridProperty(): array
    {
        $gridStart = $this->cursorDate()->startOfMonth()->startOfWeek(Carbon::MONDAY);

        return collect(range(0, 41))->map(fn (int $i) => $gridStart->copy()->addDays($i))->all();
    }

    /**
     * @return array<int, Carbon>
     */
    public function getWeekDaysProperty(): array
    {
        $weekStart = $this->weekStart();

        return collect(range(0, 6))->map(fn (int $i) => $weekStart->copy()->addDays($i))->all();
    }

    /**
     * Timeline (hour grid + positioned blocks) for the day currently in view.
     *
     * @return array{startHour: int, endHour: int, hourHeight: int, days: Collection<int, array{date: Carbon, allDay: Collection<int, CalendarItem>, blocks: Collection<int, array>}>}
     */
    public function getDayTimelineProperty(): array
    {
        return $this->buildTimeline(collect([$this->cursorDate()]));
    }

    /**
     * Timeline (hour grid + positioned blocks) for the week currently in view,
     * sharing a single hour range across all 7 days so the grid lines align.
     *
     * @return array{startHour: int, endHour: int, hourHeight: int, days: Collection<int, array{date: Carbon, allDay: Collection<int, CalendarItem>, blocks: Collection<int, array>}>}
     */
    public function getWeekTimelineProperty(): array
    {
        return $this->buildTimeline(collect($this->weekDays));
    }

    /**
     * All CalendarItem for the current teacher, grouped by every date
     * (Y-m-d) they occupy and sorted within each day (all-day items first,
     * then timed items by time-of-day).
     *
     * @return Collection<string, Collection<int, CalendarItem>>
     */
    public function getItemsByDateProperty(): Collection
    {
        return app(CalendarItemCollector::class)->forUser(Auth::user())
            ->flatMap(fn (CalendarItem $item) => collect($item->datesOccupied())->map(fn (string $date) => ['date' => $date, 'item' => $item]))
            ->groupBy('date')
            ->map(fn (Collection $pairs) => $pairs->pluck('item')
                ->sortBy(fn (CalendarItem $item) => $item->allDay ? '' : $item->startsAt->format('H:i'))
                ->values());
    }

    /**
     * @return Collection<int, CalendarItem>
     */
    public function itemsFor(Carbon $date): Collection
    {
        return $this->itemsByDate->get($date->format('Y-m-d'), collect());
    }

    private function cursorDate(): Carbon
    {
        return Carbon::parse($this->cursor);
    }

    private function weekStart(): Carbon
    {
        return $this->cursorDate()->startOfWeek(Carbon::MONDAY);
    }

    private function weekEnd(): Carbon
    {
        return $this->cursorDate()->endOfWeek(Carbon::SUNDAY);
    }

    /**
     * @param  Collection<int, Carbon>  $days
     * @return array{startHour: int, endHour: int, hourHeight: int, days: Collection<int, array{date: Carbon, allDay: Collection<int, CalendarItem>, blocks: Collection<int, array>}>}
     */
    private function buildTimeline(Collection $days): array
    {
        $itemsByDay = $days->mapWithKeys(fn (Carbon $day) => [$day->format('Y-m-d') => $this->itemsFor($day)]);

        $allTimed = $itemsByDay->flatten(1)->filter(fn (CalendarItem $item) => ! $item->allDay);

        [$startHour, $endHour] = $this->timelineBounds($allTimed);

        return [
            'startHour' => $startHour,
            'endHour' => $endHour,
            'hourHeight' => self::HOUR_HEIGHT_PX,
            'days' => $days->map(function (Carbon $day) use ($itemsByDay, $startHour) {
                $items = $itemsByDay->get($day->format('Y-m-d'));

                return [
                    'date' => $day,
                    'allDay' => $items->filter(fn (CalendarItem $item) => $item->allDay)->values(),
                    'blocks' => $this->layoutBlocks($items->filter(fn (CalendarItem $item) => ! $item->allDay)->values(), $startHour),
                ];
            })->values(),
        ];
    }

    /**
     * Hour range covering the timeline grid: business hours (7h-19h) by
     * default, extended to fit any timed item that falls outside it.
     *
     * @param  Collection<int, CalendarItem>  $timedItems
     * @return array{0: int, 1: int}
     */
    private function timelineBounds(Collection $timedItems): array
    {
        $startHour = 7;
        $endHour = 19;

        foreach ($timedItems as $item) {
            $end = $item->endsAt ?? $item->startsAt->copy()->addMinutes(30);

            $startHour = min($startHour, (int) $item->startsAt->format('H'));
            $endHour = max($endHour, (int) ceil($end->hour + $end->minute / 60));
        }

        return [max(0, $startHour), min(24, $endHour)];
    }

    /**
     * Positions timed items on a single day's hour grid, side by side when
     * they overlap (classic interval-clustering + greedy column packing).
     *
     * @param  Collection<int, CalendarItem>  $timed
     * @return Collection<int, array{item: CalendarItem, top: float, height: float, left: float, width: float}>
     */
    private function layoutBlocks(Collection $timed, int $startHour): Collection
    {
        $sorted = $timed->sortBy(fn (CalendarItem $item) => $item->startsAt)->values();

        $clusters = [];
        $currentCluster = [];
        $clusterEnd = null;

        foreach ($sorted as $item) {
            $end = $item->endsAt ?? $item->startsAt->copy()->addMinutes(30);

            if ($clusterEnd !== null && $item->startsAt->gte($clusterEnd)) {
                $clusters[] = $currentCluster;
                $currentCluster = [];
                $clusterEnd = null;
            }

            $currentCluster[] = $item;
            $clusterEnd = $clusterEnd === null || $end->gt($clusterEnd) ? $end : $clusterEnd;
        }

        if ($currentCluster !== []) {
            $clusters[] = $currentCluster;
        }

        $blocks = collect();

        foreach ($clusters as $cluster) {
            $columnEnds = [];
            $columnOf = [];

            foreach ($cluster as $i => $item) {
                $end = $item->endsAt ?? $item->startsAt->copy()->addMinutes(30);
                $placed = false;

                foreach ($columnEnds as $col => $colEnd) {
                    if ($item->startsAt->gte($colEnd)) {
                        $columnEnds[$col] = $end;
                        $columnOf[$i] = $col;
                        $placed = true;
                        break;
                    }
                }

                if (! $placed) {
                    $col = count($columnEnds);
                    $columnEnds[$col] = $end;
                    $columnOf[$i] = $col;
                }
            }

            $colCount = count($columnEnds);

            foreach ($cluster as $i => $item) {
                $end = $item->endsAt ?? $item->startsAt->copy()->addMinutes(30);
                $startMinutes = ($item->startsAt->hour * 60 + $item->startsAt->minute) - $startHour * 60;
                $durationMinutes = max(20, $item->startsAt->diffInMinutes($end));

                $blocks->push([
                    'item' => $item,
                    'top' => (float) $startMinutes / 60 * self::HOUR_HEIGHT_PX,
                    'height' => (float) $durationMinutes / 60 * self::HOUR_HEIGHT_PX,
                    'left' => (float) $columnOf[$i] / $colCount * 100,
                    'width' => (float) (1 / $colCount) * 100,
                ]);
            }
        }

        return $blocks;
    }
}
