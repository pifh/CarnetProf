<x-filament-panels::page>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex gap-2">
            <x-filament::button
                :color="$period === 'day' ? 'primary' : 'gray'"
                wire:click="setPeriod('day')"
            >
                Aujourd'hui
            </x-filament::button>
            <x-filament::button
                :color="$period === 'week' ? 'primary' : 'gray'"
                wire:click="setPeriod('week')"
            >
                Cette semaine
            </x-filament::button>
            <x-filament::button
                :color="$period === 'month' ? 'primary' : 'gray'"
                wire:click="setPeriod('month')"
            >
                Ce mois-ci
            </x-filament::button>
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
            <input type="checkbox" wire:model.live="showArchived" class="fi-checkbox-input rounded border-gray-300 dark:border-gray-600" />
            Afficher les élèves archivés
        </label>
    </div>

    <x-filament::section class="mt-6">
        @php($students = $this->getStudents())

        @if ($students->isEmpty())
            <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                Aucun anniversaire sur cette période.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left">
                            <th class="p-2">Élève</th>
                            <th class="p-2">Classe</th>
                            <th class="p-2">Date de naissance</th>
                            <th class="p-2">Âge</th>
                            <th class="p-2">Anniversaire</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($students as $student)
                            <tr class="border-t border-gray-100 dark:border-white/5">
                                <td class="p-2">
                                    {{ $student->first_name }} {{ $student->last_name }}
                                    @if ($student->is_archived)
                                        <x-filament::badge color="gray" size="sm">Archivé</x-filament::badge>
                                    @endif
                                </td>
                                <td class="p-2">
                                    <x-filament::badge :color="$student->schoolClass?->color ?? 'gray'">
                                        {{ $student->schoolClass?->name ?? '—' }}
                                    </x-filament::badge>
                                </td>
                                <td class="p-2">{{ $student->birth_date->format('d/m/Y') }}</td>
                                <td class="p-2">{{ $student->birth_date->age }} ans</td>
                                <td class="p-2">{{ $this->describeNextBirthday($student->next_birthday) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
