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

            @if ($this->subjects->isNotEmpty())
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Matière</label>
                    <x-filament::input.wrapper class="max-w-xs">
                        <x-filament::input.select wire:model.live="subjectId">
                            @foreach ($this->subjects as $subject)
                                <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            @endif
        </div>
    </x-filament::section>

    @php($students = $this->students)

    @if ($students->isEmpty())
        <x-filament::section class="mt-6">
            <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                Aucun élève dans cette classe, ou aucune classe créée.
            </div>
        </x-filament::section>
    @elseif (! $currentSessionId)
        <x-filament::section class="mt-6">
            <x-slot name="heading">Nouveau tirage</x-slot>
            <x-slot name="description">Donnez un nom à ce tirage pour pouvoir le reprendre plus tard sans perdre les élèves déjà interrogés.</x-slot>

            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Nom du tirage</label>
                    <x-filament::input.wrapper class="max-w-sm">
                        <x-filament::input type="text" wire:model="newSessionName" placeholder="ex. Interrogation orale — chapitre 3" />
                    </x-filament::input.wrapper>
                </div>

                <x-filament::button wire:click="createSession">
                    Créer et commencer
                </x-filament::button>
            </div>
        </x-filament::section>

        @if ($this->sessions->isNotEmpty())
            <x-filament::section class="mt-6">
                <x-slot name="heading">Tirages existants</x-slot>
                <x-slot name="description">Reprenez un tirage précédent — les élèves déjà interrogés ne seront pas redemandés avant que tout le monde soit passé.</x-slot>

                <div class="space-y-2">
                    @foreach ($this->sessions as $session)
                        <div class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 p-3 dark:border-white/10">
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white">{{ $session->name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $session->created_at->format('d/m/Y H:i') }} · {{ $session->picks_count }} tirage(s)
                                </div>
                            </div>
                            <div class="flex gap-1">
                                <x-filament::button size="xs" color="gray" wire:click="openSession({{ $session->id }})">
                                    Continuer
                                </x-filament::button>
                                <x-filament::button
                                    size="xs"
                                    color="danger"
                                    wire:click="deleteSession({{ $session->id }})"
                                    wire:confirm="Supprimer ce tirage et son historique ?"
                                >
                                    Supprimer
                                </x-filament::button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    @else
        @php($session = $this->sessions->firstWhere('id', $currentSessionId))

        <x-filament::section class="mt-6">
            <div class="flex flex-col items-center gap-4 py-6">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Tirage :</span>
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $session?->name }}</span>
                    <x-filament::button size="xs" color="gray" wire:click="closeSession">
                        Changer de tirage
                    </x-filament::button>
                </div>

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
                <x-slot name="heading">Nombre de fois interrogé(e) dans ce tirage</x-slot>

                <div class="space-y-1">
                    @foreach ($this->sessionHistory as $row)
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
