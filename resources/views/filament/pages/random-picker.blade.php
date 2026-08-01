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

    @php($students = $this->students)

    @if ($students->isEmpty())
        <x-filament::section class="mt-6">
            <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                Aucun élève dans cette classe, ou aucune classe créée.
            </div>
        </x-filament::section>
    @else
        <x-filament::section class="mt-6">
            <div class="flex flex-col items-center gap-4 py-6">
                @if ($lastPickedStudentId)
                    @php($picked = $students->firstWhere('id', $lastPickedStudentId))
                    <div class="text-3xl font-bold text-gray-900 dark:text-white">
                        {{ $picked?->first_name }} {{ $picked?->last_name }}
                    </div>
                @else
                    <div class="text-lg text-gray-500 dark:text-gray-400">
                        Cliquez sur « Tirer au sort » pour interroger un élève.
                    </div>
                @endif

                <div class="flex gap-2">
                    <x-filament::button size="lg" wire:click="pick">
                        Tirer au sort
                    </x-filament::button>

                    <x-filament::button size="lg" color="gray" wire:click="resetRound">
                        Réinitialiser le tour
                    </x-filament::button>
                </div>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ count($pickedStudentIdsThisRound) }} / {{ $students->count() - count($excludedStudentIds) }} élève(s) interrogé(s) ce tour.
                </p>
            </div>
        </x-filament::section>

        <div class="mt-6 grid gap-6 md:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Élèves absents (exclus du tirage)</x-slot>

                <div class="flex flex-wrap gap-2">
                    @foreach ($students as $student)
                        <x-filament::button
                            size="xs"
                            :color="in_array($student->id, $excludedStudentIds, true) ? 'danger' : 'gray'"
                            wire:click="toggleExcluded({{ $student->id }})"
                        >
                            {{ $student->first_name }} {{ $student->last_name }}
                        </x-filament::button>
                    @endforeach
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Nombre de fois interrogé(e)</x-slot>

                <div class="space-y-1">
                    @foreach ($this->history as $row)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-700 dark:text-gray-300">{{ $row['student']->first_name }} {{ $row['student']->last_name }}</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $row['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
