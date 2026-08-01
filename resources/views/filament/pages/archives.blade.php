<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Classes archivées</x-slot>

        @php($classes = $this->archivedClasses)

        @if ($classes->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">Aucune classe archivée.</p>
        @else
            <div class="space-y-2">
                @foreach ($classes as $schoolClass)
                    <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 p-3 dark:border-white/10" wire:key="class-{{ $schoolClass->id }}">
                        <div>
                            <div class="font-medium text-gray-900 dark:text-white">
                                {{ $schoolClass->name }}
                                <span class="text-gray-500 dark:text-gray-400">— {{ $schoolClass->school_year }}</span>
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $schoolClass->students_count }} élève(s)
                                @if ($schoolClass->archived_at)
                                    · archivée le {{ $schoolClass->archived_at->format('d/m/Y') }}
                                @endif
                            </div>
                        </div>

                        <x-filament::button size="sm" color="gray" wire:click="unarchiveClass({{ $schoolClass->id }})">
                            Désarchiver
                        </x-filament::button>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament::section class="mt-6">
        <x-slot name="heading">Élèves archivés individuellement</x-slot>
        <x-slot name="description">Élèves archivés dont la classe reste active (ex. départ en cours d'année).</x-slot>

        @php($students = $this->archivedStudents)

        @if ($students->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">Aucun élève archivé individuellement.</p>
        @else
            <div class="space-y-2">
                @foreach ($students as $student)
                    <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 p-3 dark:border-white/10" wire:key="student-{{ $student->id }}">
                        <div>
                            <div class="font-medium text-gray-900 dark:text-white">{{ $student->first_name }} {{ $student->last_name }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $student->schoolClass?->name }}
                                @if ($student->archived_at)
                                    · archivé(e) le {{ $student->archived_at->format('d/m/Y') }}
                                @endif
                            </div>
                        </div>

                        <x-filament::button size="sm" color="gray" wire:click="unarchiveStudent({{ $student->id }})">
                            Désarchiver
                        </x-filament::button>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
