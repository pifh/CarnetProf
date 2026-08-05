<x-filament-panels::page>
    <x-filament::section>
        <div>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Classe</label>
            <x-filament::input.wrapper class="max-w-sm">
                <x-filament::input.select wire:model.live="schoolClassId">
                    @foreach ($this->schoolClasses as $schoolClass)
                        <option value="{{ $schoolClass->id }}">{{ $schoolClass->name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
    </x-filament::section>

    <x-filament::section class="mt-4">
        <x-slot name="heading">Seuils avant sanction</x-slot>
        <x-slot name="description">Le compteur « depuis la dernière réinitialisation » passe en rouge à partir de ce nombre, pour vous rappeler de sanctionner.</x-slot>

        <div class="flex flex-wrap items-end gap-4">
            @foreach ($this->categoryLabels as $key => $label)
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</label>
                    <x-filament::input.wrapper class="max-w-[8rem]">
                        <x-filament::input type="number" min="1" wire:model="thresholds.{{ $key }}" />
                    </x-filament::input.wrapper>
                </div>
            @endforeach

            <x-filament::button wire:click="saveThresholds">
                Enregistrer les seuils
            </x-filament::button>
        </div>
    </x-filament::section>

    @php($students = $this->students)

    @if ($students->isEmpty())
        <x-filament::section class="mt-4">
            <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                Aucun élève dans cette classe, ou aucune classe créée.
            </div>
        </x-filament::section>
    @else
        <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full min-w-[900px] border-collapse text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-gray-900">
                        <th class="p-2 text-left font-medium text-gray-700 dark:text-gray-300">Élève</th>
                        @foreach ($this->categoryLabels as $category => $label)
                            <th class="p-2 text-center font-medium text-gray-700 dark:text-gray-300">
                                <div>{{ $label }}</div>
                                <x-filament::button
                                    size="xs"
                                    color="gray"
                                    class="mt-1"
                                    wire:click="resetClass('{{ $category }}')"
                                    wire:confirm="Réinitialiser le compteur « {{ $label }} » pour toute la classe ?"
                                >
                                    Réinitialiser la classe
                                </x-filament::button>
                            </th>
                        @endforeach
                        <th class="p-2 text-center font-medium text-gray-700 dark:text-gray-300">Fiche</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($students as $student)
                        <tr class="border-b border-gray-100 last:border-0 dark:border-white/5" wire:key="discipline-student-{{ $student->id }}">
                            <td class="p-2">
                                <x-student-name :student="$student" />
                            </td>
                            @foreach ($this->categoryLabels as $category => $label)
                                @php($counts = $this->countsFor($student->id, $category))
                                @php($threshold = $thresholds[$category] ?? 1)
                                <td class="p-2 text-center" wire:key="cell-{{ $student->id }}-{{ $category }}">
                                    <div class="flex flex-col items-center gap-1">
                                        <div class="flex items-baseline gap-1">
                                            <span
                                                @class([
                                                    'text-lg font-semibold',
                                                    'text-danger-600 dark:text-danger-400' => $counts['trip'] >= $threshold,
                                                    'text-gray-700 dark:text-gray-300' => $counts['trip'] < $threshold,
                                                ])
                                            >
                                                {{ $counts['trip'] }}
                                            </span>
                                            <span class="text-[11px] text-gray-400 dark:text-gray-500">
                                                (total {{ $counts['total'] }})
                                            </span>
                                        </div>

                                        <div class="flex items-center gap-1">
                                            <x-filament::icon-button
                                                icon="heroicon-o-plus"
                                                color="primary"
                                                label="Ajouter un {{ $label }}"
                                                wire:click="log({{ $student->id }}, '{{ $category }}')"
                                            />
                                            <x-filament::icon-button
                                                icon="heroicon-o-arrow-path"
                                                color="gray"
                                                label="Réinitialiser"
                                                wire:click="resetStudent({{ $student->id }}, '{{ $category }}')"
                                                wire:confirm="Réinitialiser le compteur « {{ $label }} » de {{ $student->full_name }} ?"
                                            />
                                        </div>
                                    </div>
                                </td>
                            @endforeach
                            <td class="p-2 text-center">
                                <x-filament::icon-button
                                    icon="heroicon-o-clock"
                                    label="Voir l'historique"
                                    wire:click="showHistory({{ $student->id }})"
                                />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($historyStudentId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 lg:left-(--sidebar-width)" wire:click.self="closeHistory">
            <div class="max-h-[80vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
                <div class="flex items-center justify-between gap-4">
                    <h3 class="text-lg font-semibold text-gray-950 dark:text-white">
                        {{ $this->historyStudent?->full_name }}
                    </h3>
                    <x-filament::icon-button icon="heroicon-o-x-mark" label="Fermer" wire:click="closeHistory" />
                </div>

                <div class="mt-4 space-y-4">
                    @foreach ($this->categoryLabels as $category => $label)
                        @php($dates = $this->historyByCategory->get($category, collect()))
                        <div>
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $label }} ({{ $dates->count() }})
                            </div>

                            @if ($dates->isEmpty())
                                <p class="text-xs text-gray-400 dark:text-gray-600">Aucun.</p>
                            @else
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @foreach ($dates as $entry)
                                        <span
                                            class="inline-flex items-center gap-0.5 rounded bg-gray-100 py-0.5 pl-1.5 text-[11px] text-gray-600 dark:bg-white/10 dark:text-gray-300"
                                            wire:key="history-entry-{{ $entry->id }}"
                                        >
                                            {{ $entry->occurred_at->format('d/m/Y') }}
                                            <x-filament::icon-button
                                                icon="heroicon-o-x-mark"
                                                size="xs"
                                                color="gray"
                                                label="Supprimer cette entrée du {{ $entry->occurred_at->format('d/m/Y') }}"
                                                wire:click="deleteEntry({{ $entry->id }})"
                                                wire:confirm="Supprimer cette entrée du {{ $entry->occurred_at->format('d/m/Y') }} ? En cas d'erreur de saisie uniquement."
                                            />
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
