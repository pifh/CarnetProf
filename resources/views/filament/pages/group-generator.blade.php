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

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Période (pour le niveau)</label>
                <x-filament::input.wrapper class="max-w-xs">
                    <x-filament::input.select wire:model.live="termId">
                        <option value="">Année complète</option>
                        @foreach ($this->terms as $term)
                            <option value="{{ $term->id }}">{{ $term->label }}</option>
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
        @php($eligibleStudents = $students->reject(fn ($student) => in_array($student->id, $excludedStudentIds, true)))

        <x-filament::section class="mt-6">
            <x-slot name="heading">Critères de génération</x-slot>
            <x-slot name="description">Combinez librement les critères ci-dessous — ils s'appliquent tous en même temps.</x-slot>

            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Niveau</label>
                    <x-filament::input.wrapper class="max-w-xs">
                        <x-filament::input.select wire:model.live="levelMode">
                            <option value="none">Aucun</option>
                            <option value="balance">Équilibrer les niveaux</option>
                            <option value="homogeneous">Faire des groupes de niveau</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Mixité</label>
                    <x-filament::input.wrapper class="max-w-xs">
                        <x-filament::input.select wire:model.live="genderMode">
                            <option value="none">Aucune contrainte</option>
                            <option value="mixed_balanced">Mixtes équilibrés</option>
                            <option value="single_sex">Non mixte</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <label class="flex items-center gap-2 pb-2 text-sm text-gray-600 dark:text-gray-400">
                    <input type="checkbox" wire:model.live="avoidRepeats" class="fi-checkbox-input rounded border-gray-300 dark:border-gray-600" />
                    Éviter de répéter les groupages précédents
                </label>
            </div>
        </x-filament::section>

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

        @if ($eligibleStudents->isNotEmpty())
            <x-filament::section class="mt-6">
                <x-slot name="heading">Préplacement (optionnel)</x-slot>
                <x-slot name="description">Forcez certains élèves dans un groupe précis avant de générer — le reste de la classe est réparti autour d'eux.</x-slot>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($eligibleStudents as $student)
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $student->first_name }} {{ $student->last_name }}</span>
                            <x-filament::input.wrapper class="max-w-[9rem]">
                                <x-filament::input.select wire:model.live="lockedPlacements.{{ $student->id }}">
                                    <option value="">Non assigné</option>
                                    @for ($i = 0; $i < $this->resolvedGroupCount; $i++)
                                        <option value="{{ $i }}">Groupe {{ $i + 1 }}</option>
                                    @endfor
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <x-filament::section>
                    <x-slot name="heading">Élèves à garder ensemble</x-slot>

                    <div class="flex flex-wrap items-end gap-2">
                        <x-filament::input.wrapper class="max-w-[10rem]">
                            <x-filament::input.select wire:model="keepTogetherStudentA">
                                <option value="">Élève A</option>
                                @foreach ($eligibleStudents as $student)
                                    <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                        <x-filament::input.wrapper class="max-w-[10rem]">
                            <x-filament::input.select wire:model="keepTogetherStudentB">
                                <option value="">Élève B</option>
                                @foreach ($eligibleStudents as $student)
                                    <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                        <x-filament::button size="sm" color="gray" wire:click="addKeepTogetherPair">Ajouter</x-filament::button>
                    </div>

                    @if (! empty($keepTogetherPairs))
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($keepTogetherPairs as $index => $pair)
                                @php($studentA = $students->firstWhere('id', $pair[0]))
                                @php($studentB = $students->firstWhere('id', $pair[1]))
                                <x-filament::badge color="success">
                                    {{ $studentA?->first_name }} {{ $studentA?->last_name }} ↔ {{ $studentB?->first_name }} {{ $studentB?->last_name }}
                                    <button type="button" wire:click="removeKeepTogetherPair({{ $index }})" class="ml-1">×</button>
                                </x-filament::badge>
                            @endforeach
                        </div>
                    @endif
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Élèves à séparer</x-slot>

                    <div class="flex flex-wrap items-end gap-2">
                        <x-filament::input.wrapper class="max-w-[10rem]">
                            <x-filament::input.select wire:model="keepApartStudentA">
                                <option value="">Élève A</option>
                                @foreach ($eligibleStudents as $student)
                                    <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                        <x-filament::input.wrapper class="max-w-[10rem]">
                            <x-filament::input.select wire:model="keepApartStudentB">
                                <option value="">Élève B</option>
                                @foreach ($eligibleStudents as $student)
                                    <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                        <x-filament::button size="sm" color="gray" wire:click="addKeepApartPair">Ajouter</x-filament::button>
                    </div>

                    @if (! empty($keepApartPairs))
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($keepApartPairs as $index => $pair)
                                @php($studentA = $students->firstWhere('id', $pair[0]))
                                @php($studentB = $students->firstWhere('id', $pair[1]))
                                <x-filament::badge color="danger">
                                    {{ $studentA?->first_name }} {{ $studentA?->last_name }} ↔ {{ $studentB?->first_name }} {{ $studentB?->last_name }}
                                    <button type="button" wire:click="removeKeepApartPair({{ $index }})" class="ml-1">×</button>
                                </x-filament::badge>
                            @endforeach
                        </div>
                    @endif
                </x-filament::section>
            </div>
        @endif

        @if (! empty($generationConflicts))
            <x-filament::section class="mt-6" icon="heroicon-o-exclamation-triangle" icon-color="warning">
                <x-slot name="heading">Contraintes non totalement respectées</x-slot>

                <ul class="list-inside list-disc space-y-1 text-sm text-warning-600 dark:text-warning-400">
                    @foreach ($generationConflicts as $conflict)
                        <li>{{ $this->conflictLabel($conflict) }}</li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endif

        @php($groups = $this->generatedGroupsDisplay)

        @if (! empty($groups))
            <x-filament::section class="mt-6">
                <x-slot name="heading">Groupes générés</x-slot>
                <x-slot name="description">Ces groupes sont faits pour une activité précise — donnez-lui un nom avant d'enregistrer.</x-slot>

                <div class="mb-4 flex flex-wrap items-end gap-4">
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Nom de l'activité</label>
                        <x-filament::input.wrapper class="max-w-sm">
                            <x-filament::input type="text" wire:model.live="activityName" placeholder="ex. Exposé sur la Révolution française" />
                        </x-filament::input.wrapper>
                    </div>

                    <label class="flex items-center gap-2 pb-2 text-sm text-gray-600 dark:text-gray-400">
                        <input type="checkbox" wire:model.live="isGraded" class="fi-checkbox-input rounded border-gray-300 dark:border-gray-600" />
                        Cette activité est notée
                    </label>

                    @if ($isGraded)
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Barème</label>
                            <x-filament::input.wrapper class="max-w-[7rem]">
                                <x-filament::input type="number" step="0.5" min="1" wire:model.live="maxScore" />
                            </x-filament::input.wrapper>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Coefficient</label>
                            <x-filament::input.wrapper class="max-w-[7rem]">
                                <x-filament::input type="number" step="0.5" min="0.1" wire:model.live="coefficient" />
                            </x-filament::input.wrapper>
                        </div>
                    @endif

                    <x-filament::button color="success" wire:click="save">
                        Enregistrer ces groupes
                    </x-filament::button>
                </div>

                @if ($isGraded && ! $termId)
                    <p class="mb-4 text-sm text-warning-600 dark:text-warning-400">
                        Choisissez un trimestre en haut de page pour pouvoir noter cette activité.
                    </p>
                @endif

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($groups as $index => $group)
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <span class="font-medium text-gray-900 dark:text-white">{{ $group['name'] }}</span>
                                <div class="flex gap-1">
                                    @if ($group['average'] !== null)
                                        <x-filament::badge color="gray">{{ number_format($group['average'], 2) }}/20</x-filament::badge>
                                    @endif
                                    @if ($group['sexCounts']['f'] > 0 || $group['sexCounts']['m'] > 0)
                                        <x-filament::badge color="gray">♀ {{ $group['sexCounts']['f'] }} · ♂ {{ $group['sexCounts']['m'] }}</x-filament::badge>
                                    @endif
                                </div>
                            </div>
                            <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                @foreach ($group['students'] as $student)
                                    <li>{{ $student->first_name }} {{ $student->last_name }}</li>
                                @endforeach
                            </ul>

                            @if ($isGraded)
                                <div class="mt-3 flex items-center gap-2">
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Note du groupe</label>
                                    <x-filament::input.wrapper class="max-w-[7rem]">
                                        <x-filament::input type="number" step="0.25" min="0" :max="$maxScore" wire:model.live="groupScores.{{ $index }}" />
                                    </x-filament::input.wrapper>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">/ {{ $maxScore }}</span>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        @if ($this->history->isNotEmpty())
            <x-filament::section class="mt-6">
                <x-slot name="heading">Historique des générations</x-slot>
                <x-slot name="description">Pour cette classe{{ $subjectId ? ' et cette matière' : '' }} — les groupes déjà créés restent disponibles dans « Groupes de travail » même après suppression d'une entrée ici.</x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left">
                                <th class="p-2">Date</th>
                                <th class="p-2">Activité</th>
                                <th class="p-2">Critères</th>
                                <th class="p-2">Élèves</th>
                                <th class="p-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->history as $generation)
                                <tr class="border-t border-gray-100 dark:border-white/5">
                                    <td class="p-2">{{ $generation->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="p-2">
                                        {{ $generation->activity_name ?? '—' }}
                                        @if ($generation->evaluation)
                                            <x-filament::badge color="success" class="ml-1">
                                                Noté /{{ rtrim(rtrim(number_format($generation->evaluation->max_score, 2), '0'), '.') }}
                                            </x-filament::badge>
                                        @endif
                                    </td>
                                    <td class="p-2">{{ $this->historySummary($generation) }}</td>
                                    <td class="p-2">{{ collect($generation->groups)->flatten()->count() }}</td>
                                    <td class="p-2">
                                        <div class="flex gap-1">
                                            <x-filament::button
                                                size="xs"
                                                color="gray"
                                                wire:click="toggleViewGeneration({{ $generation->id }})"
                                            >
                                                {{ $viewingGenerationId === $generation->id ? 'Fermer' : 'Voir' }}
                                            </x-filament::button>
                                            <x-filament::button
                                                size="xs"
                                                color="danger"
                                                wire:click="deleteGeneration({{ $generation->id }})"
                                                wire:confirm="Supprimer cet historique ? Les groupes déjà créés (et les notes déjà données) resteront disponibles."
                                            >
                                                Supprimer
                                            </x-filament::button>
                                        </div>
                                    </td>
                                </tr>

                                @if ($viewingGenerationId === $generation->id)
                                    <tr>
                                        <td colspan="5" class="border-t border-gray-100 p-4 dark:border-white/5">
                                            @php($viewedGroups = $this->viewedGenerationGroups)

                                            @if (empty($viewedGroups))
                                                <p class="text-sm text-gray-500 dark:text-gray-400">Aucun groupe à afficher.</p>
                                            @else
                                                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                                    @foreach ($viewedGroups as $group)
                                                        <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                                                            <div class="mb-2 flex items-center justify-between gap-2">
                                                                <span class="font-medium text-gray-900 dark:text-white">{{ $group['name'] }}</span>
                                                                <div class="flex gap-1">
                                                                    @if ($group['score'] !== null)
                                                                        <x-filament::badge color="success">
                                                                            {{ rtrim(rtrim(number_format($group['score'], 2), '0'), '.') }}
                                                                            @if ($generation->evaluation)
                                                                                / {{ rtrim(rtrim(number_format($generation->evaluation->max_score, 2), '0'), '.') }}
                                                                            @endif
                                                                        </x-filament::badge>
                                                                    @endif
                                                                    @if ($group['sexCounts']['f'] > 0 || $group['sexCounts']['m'] > 0)
                                                                        <x-filament::badge color="gray">♀ {{ $group['sexCounts']['f'] }} · ♂ {{ $group['sexCounts']['m'] }}</x-filament::badge>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                                                @foreach ($group['students'] as $student)
                                                                    <li>{{ $student->first_name }} {{ $student->last_name }}</li>
                                                                @endforeach
                                                            </ul>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
