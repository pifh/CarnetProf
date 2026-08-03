<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap gap-3 text-sm text-gray-600 dark:text-gray-400">
                <x-filament::badge :color="$evaluation->schoolClass?->color ?? 'gray'">
                    {{ $evaluation->schoolClass?->name }}
                </x-filament::badge>
                <span>{{ $evaluation->subject?->name ?? 'Matière non définie' }}</span>
                <span>{{ $evaluation->term?->label }}</span>
                <span>Barème /{{ rtrim(rtrim(number_format($evaluation->max_score, 2), '0'), '.') }}</span>
                <span>Coefficient {{ rtrim(rtrim(number_format($evaluation->coefficient, 2), '0'), '.') }}</span>
                @if ($average = $this->getClassAverage())
                    <span class="font-medium text-gray-900 dark:text-white">Moyenne de classe : {{ $average }}/20</span>
                @endif
            </div>

            <x-filament::button color="gray" wire:click="exportCsv">
                Exporter en CSV
            </x-filament::button>
        </div>
    </x-filament::section>

    <x-filament::section class="mt-6">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left">
                        <th class="p-2">Élève</th>
                        <th class="p-2">Note</th>
                        <th class="p-2">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($this->groups->isNotEmpty())
                        @foreach ($this->groups as $group)
                            <tr class="border-t border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5" wire:key="group-{{ $group['id'] }}">
                                <td class="p-2" colspan="3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-gray-900 dark:text-white">{{ $group['name'] }}</span>
                                        <div class="flex items-center gap-1">
                                            <x-filament::input.wrapper class="max-w-[6rem]">
                                                <x-filament::input
                                                    type="text"
                                                    inputmode="decimal"
                                                    placeholder="Note groupe"
                                                    wire:change="updateGroupScore({{ $group['id'] }}, $event.target.value)"
                                                />
                                            </x-filament::input.wrapper>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                / {{ rtrim(rtrim(number_format($evaluation->max_score, 2), '0'), '.') }} — copiée à tout le groupe, modifiable ensuite élève par élève
                                            </span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @foreach ($group['students'] as $student)
                                @include('filament.pages.partials.evaluation-grade-row', ['student' => $student, 'evaluation' => $evaluation])
                            @endforeach
                        @endforeach

                        @if ($this->ungroupedStudents->isNotEmpty())
                            <tr class="border-t border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                                <td class="p-2 font-medium text-gray-500 dark:text-gray-400" colspan="3">Élèves sans groupe</td>
                            </tr>
                            @foreach ($this->ungroupedStudents as $student)
                                @include('filament.pages.partials.evaluation-grade-row', ['student' => $student, 'evaluation' => $evaluation])
                            @endforeach
                        @endif
                    @else
                        @foreach ($this->students as $student)
                            @include('filament.pages.partials.evaluation-grade-row', ['student' => $student, 'evaluation' => $evaluation])
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
