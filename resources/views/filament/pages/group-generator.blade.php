<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-wrap items-end gap-4">
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

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Mode</label>
                <x-filament::input.wrapper class="max-w-xs">
                    <x-filament::input.select wire:model.live="mode">
                        <option value="count">Nombre de groupes</option>
                        <option value="size">Taille des groupes</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            @if ($mode === 'count')
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Nombre de groupes</label>
                    <x-filament::input.wrapper class="max-w-[8rem]">
                        <x-filament::input type="number" min="1" wire:model.live="groupCount" />
                    </x-filament::input.wrapper>
                </div>
            @else
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Élèves par groupe</label>
                    <x-filament::input.wrapper class="max-w-[8rem]">
                        <x-filament::input type="number" min="1" wire:model.live="groupSize" />
                    </x-filament::input.wrapper>
                </div>
            @endif

            <x-filament::button size="lg" wire:click="generate">
                Générer
            </x-filament::button>
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

        @php($groups = $this->generatedGroupsDisplay)

        @if (! empty($groups))
            <x-filament::section class="mt-6">
                <div class="flex items-center justify-between">
                    <x-slot name="heading">Groupes générés</x-slot>

                    <x-filament::button color="success" wire:click="save">
                        Enregistrer ces groupes
                    </x-filament::button>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($groups as $group)
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                            <div class="mb-2 font-medium text-gray-900 dark:text-white">{{ $group['name'] }}</div>
                            <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                @foreach ($group['students'] as $student)
                                    <li>{{ $student->first_name }} {{ $student->last_name }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
