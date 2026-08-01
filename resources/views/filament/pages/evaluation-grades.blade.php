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
                    @foreach ($this->students as $student)
                        <tr class="border-t border-gray-100 dark:border-white/5" wire:key="student-{{ $student->id }}">
                            <td class="p-2">{{ $student->first_name }} {{ $student->last_name }}</td>
                            <td class="p-2">
                                <div class="flex items-center gap-1">
                                    <x-filament::input.wrapper class="max-w-[6rem]">
                                        <x-filament::input
                                            type="text"
                                            inputmode="decimal"
                                            :value="$student->grade?->score"
                                            wire:change="updateScore({{ $student->id }}, $event.target.value)"
                                        />
                                    </x-filament::input.wrapper>
                                    <span class="text-gray-500 dark:text-gray-400">/ {{ rtrim(rtrim(number_format($evaluation->max_score, 2), '0'), '.') }}</span>
                                </div>
                            </td>
                            <td class="p-2">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ([
                                        'absent' => 'Absent',
                                        'exempted' => 'Dispensé',
                                        'to_retake' => 'À rattraper',
                                        'not_graded' => 'Non noté',
                                    ] as $statusValue => $statusLabel)
                                        <x-filament::button
                                            size="xs"
                                            :color="$student->grade?->status === $statusValue ? 'danger' : 'gray'"
                                            wire:click="setStatus({{ $student->id }}, '{{ $statusValue }}')"
                                        >
                                            {{ $statusLabel }}
                                        </x-filament::button>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
