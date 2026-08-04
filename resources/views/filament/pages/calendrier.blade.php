<x-filament-panels::page>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            <x-filament::button size="sm" color="gray" wire:click="today">Aujourd'hui</x-filament::button>

            <x-filament::icon-button icon="heroicon-o-chevron-left" wire:click="previous" label="Précédent" />
            <x-filament::icon-button icon="heroicon-o-chevron-right" wire:click="next" label="Suivant" />

            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">{{ $this->headingLabel }}</h2>
        </div>

        <div class="flex items-center gap-2">
            <x-filament::button.group>
                <x-filament::button size="sm" :color="$mode === 'day' ? 'primary' : 'gray'" wire:click="setMode('day')">Jour</x-filament::button>
                <x-filament::button size="sm" :color="$mode === 'week' ? 'primary' : 'gray'" wire:click="setMode('week')">Semaine</x-filament::button>
                <x-filament::button size="sm" :color="$mode === 'month' ? 'primary' : 'gray'" wire:click="setMode('month')">Mois</x-filament::button>
            </x-filament::button.group>

            <x-filament::button size="sm" color="gray" tag="a" href="{{ \App\Filament\Pages\CalendrierReglages::getUrl() }}" icon="heroicon-o-cog-6-tooth">
                Réglages
            </x-filament::button>
        </div>
    </div>

    @php($today = \Illuminate\Support\Carbon::today())

    @if ($mode === 'month')
        <div class="mt-4 overflow-x-auto">
            <div class="grid min-w-[840px] grid-cols-7 gap-px overflow-hidden rounded-lg border border-gray-200 bg-gray-200 text-xs font-medium text-gray-500 dark:border-white/10 dark:bg-white/10 dark:text-gray-400">
                @foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $weekday)
                    <div class="bg-gray-50 px-2 py-1 text-center dark:bg-gray-900">{{ $weekday }}</div>
                @endforeach
            </div>

            <div class="grid min-w-[840px] grid-cols-7 grid-rows-6 gap-px overflow-hidden rounded-b-lg border border-t-0 border-gray-200 bg-gray-200 dark:border-white/10 dark:bg-white/10" wire:key="month-{{ $this->cursor }}">
                @foreach ($this->monthGrid as $day)
                    @php($items = $this->itemsFor($day))
                    <div class="min-h-[7rem] bg-white p-1 dark:bg-gray-900" wire:key="day-{{ $day->format('Ymd') }}">
                        <button
                            type="button"
                            wire:click="goToDay('{{ $day->format('Y-m-d') }}')"
                            @class([
                                'flex h-6 w-6 items-center justify-center rounded-full text-xs',
                                'text-gray-300 dark:text-white/20' => $day->month !== \Illuminate\Support\Carbon::parse($cursor)->month,
                                'font-semibold text-primary-600 dark:text-primary-400' => $day->isSameDay($today) && $day->month === \Illuminate\Support\Carbon::parse($cursor)->month,
                                'text-gray-700 dark:text-gray-300' => ! $day->isSameDay($today) && $day->month === \Illuminate\Support\Carbon::parse($cursor)->month,
                            ])
                        >
                            {{ $day->day }}
                        </button>

                        <div class="mt-1 flex flex-col gap-0.5">
                            @foreach ($items->take(3) as $item)
                                @include('filament.pages.calendrier-item-chip', ['item' => $item])
                            @endforeach

                            @if ($items->count() > 3)
                                <button type="button" wire:click="goToDay('{{ $day->format('Y-m-d') }}')" class="px-1 text-left text-[11px] text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                                    +{{ $items->count() - 3 }} de plus
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @elseif ($mode === 'week')
        @php($timeline = $this->weekTimeline)
        <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
            <div class="min-w-[980px]" wire:key="week-{{ $this->cursor }}">
                <div class="flex border-b border-gray-200 dark:border-white/10">
                    <div class="w-14 shrink-0"></div>
                    @foreach ($timeline['days'] as $day)
                        <button
                            type="button"
                            wire:click="goToDay('{{ $day['date']->format('Y-m-d') }}')"
                            @class([
                                'min-w-0 flex-1 py-1.5 text-center text-sm font-medium',
                                'text-primary-600 dark:text-primary-400' => $day['date']->isSameDay($today),
                                'text-gray-700 dark:text-gray-300' => ! $day['date']->isSameDay($today),
                            ])
                        >
                            {{ ucfirst($day['date']->translatedFormat('D j')) }}
                        </button>
                    @endforeach
                </div>

                @if (collect($timeline['days'])->contains(fn ($day) => $day['allDay']->isNotEmpty()))
                    <div class="flex border-b border-gray-200 dark:border-white/10">
                        <div class="w-14 shrink-0"></div>
                        @foreach ($timeline['days'] as $day)
                            <div class="flex min-w-0 flex-1 flex-col gap-0.5 p-1" wire:key="week-allday-{{ $day['date']->format('Ymd') }}">
                                @foreach ($day['allDay'] as $item)
                                    @include('filament.pages.calendrier-item-chip', ['item' => $item, 'chipClass' => 'px-1.5 py-1 text-xs'])
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="flex">
                    <div class="w-14 shrink-0 text-right text-xs text-gray-400 dark:text-gray-500">
                        @for ($hour = $timeline['startHour']; $hour < $timeline['endHour']; $hour++)
                            <div class="border-t border-gray-100 pr-2 dark:border-white/5" style="height: {{ $timeline['hourHeight'] }}px;">{{ sprintf('%02d:00', $hour) }}</div>
                        @endfor
                    </div>

                    @foreach ($timeline['days'] as $day)
                        <div class="relative min-w-0 flex-1 border-l border-gray-200 dark:border-white/10" style="height: {{ ($timeline['endHour'] - $timeline['startHour']) * $timeline['hourHeight'] }}px;" wire:key="week-grid-{{ $day['date']->format('Ymd') }}">
                            @for ($hour = $timeline['startHour']; $hour < $timeline['endHour']; $hour++)
                                <div class="absolute inset-x-0 border-t border-gray-100 dark:border-white/5" style="top: {{ ($hour - $timeline['startHour']) * $timeline['hourHeight'] }}px;"></div>
                            @endfor

                            @foreach ($day['blocks'] as $block)
                                @include('filament.pages.calendrier-timed-block', $block)
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        @php($timeline = $this->dayTimeline)
        @php($day = $timeline['days'][0])
        <div class="mt-4 rounded-lg border border-gray-200 dark:border-white/10" wire:key="day-view-{{ $this->cursor }}">
            @if ($day['allDay']->isNotEmpty())
                <div class="flex flex-wrap gap-1 border-b border-gray-200 p-2 dark:border-white/10">
                    @foreach ($day['allDay'] as $item)
                        @include('filament.pages.calendrier-item-chip', ['item' => $item, 'chipClass' => 'px-2 py-1 text-xs'])
                    @endforeach
                </div>
            @endif

            <div class="flex">
                <div class="w-14 shrink-0 text-right text-xs text-gray-400 dark:text-gray-500">
                    @for ($hour = $timeline['startHour']; $hour < $timeline['endHour']; $hour++)
                        <div class="border-t border-gray-100 pr-2 dark:border-white/5" style="height: {{ $timeline['hourHeight'] }}px;">{{ sprintf('%02d:00', $hour) }}</div>
                    @endfor
                </div>

                <div class="relative flex-1 border-l border-gray-200 dark:border-white/10" style="height: {{ ($timeline['endHour'] - $timeline['startHour']) * $timeline['hourHeight'] }}px;">
                    @for ($hour = $timeline['startHour']; $hour < $timeline['endHour']; $hour++)
                        <div class="absolute inset-x-0 border-t border-gray-100 dark:border-white/5" style="top: {{ ($hour - $timeline['startHour']) * $timeline['hourHeight'] }}px;"></div>
                    @endfor

                    @if ($day['blocks']->isEmpty() && $day['allDay']->isEmpty())
                        <p class="absolute inset-x-0 top-4 text-center text-sm text-gray-400 dark:text-gray-600">Rien de prévu ce jour-là.</p>
                    @endif

                    @foreach ($day['blocks'] as $block)
                        @include('filament.pages.calendrier-timed-block', $block)
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
