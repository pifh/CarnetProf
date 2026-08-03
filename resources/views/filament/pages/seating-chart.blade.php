<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Plan de base</label>
                <x-filament::input.wrapper class="max-w-sm">
                    <x-filament::input.select wire:model.live="planId">
                        @foreach ($this->plans as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

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
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Application (date)</label>
                <x-filament::input.wrapper class="max-w-sm">
                    <x-filament::input.select wire:model.live="applicationId">
                        @foreach ($this->applications as $application)
                            <option value="{{ $application->id }}">
                                {{ $application->effective_date?->format('d/m/Y') ?? 'Sans date' }}{{ $application->is_archived ? ' (archivée)' : '' }}
                            </option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <label class="flex items-center gap-2 pb-2 text-sm text-gray-600 dark:text-gray-400">
                <input type="checkbox" wire:model.live="showArchivedApplications" class="fi-checkbox-input rounded border-gray-300 dark:border-gray-600" />
                Afficher les applications archivées
            </label>
        </div>

        <div class="mt-4 flex flex-wrap items-end gap-4">
            <x-filament::button color="gray" wire:click="createPlan">
                Nouveau plan
            </x-filament::button>

            <x-filament::button color="gray" wire:click="duplicatePlan">
                Dupliquer ce plan
            </x-filament::button>

            <x-filament::button
                color="danger"
                wire:click="deletePlan"
                onclick="confirm('Supprimer ce plan, ses applications et leurs placements ?') || event.stopImmediatePropagation()"
            >
                Supprimer ce plan
            </x-filament::button>

            <span class="mx-1 h-6 border-l border-gray-200 dark:border-white/10"></span>

            <x-filament::button color="gray" wire:click="createApplication">
                Nouvelle application
            </x-filament::button>

            <x-filament::button color="gray" wire:click="toggleArchiveApplication">
                {{ $this->currentApplication?->is_archived ? 'Désarchiver cette application' : 'Archiver cette application' }}
            </x-filament::button>

            <x-filament::button
                color="danger"
                wire:click="deleteApplication"
                onclick="confirm('Supprimer cette application et ses placements ?') || event.stopImmediatePropagation()"
            >
                Supprimer cette application
            </x-filament::button>
        </div>

        <div class="mt-4 flex flex-wrap items-end gap-4">
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Nom du plan</label>
                <x-filament::input.wrapper class="max-w-sm">
                    <x-filament::input
                        type="text"
                        value="{{ $this->currentPlan?->name }}"
                        wire:change="renamePlan($event.target.value)"
                        wire:key="plan-name-{{ $planId }}"
                    />
                </x-filament::input.wrapper>
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Bureau du professeur</label>
                <x-filament::input.wrapper class="max-w-[10rem]">
                    <x-filament::input.select
                        wire:change="setTeacherDeskPosition($event.target.value)"
                        wire:key="teacher-desk-{{ $planId }}"
                    >
                        <option value="" @selected(! $teacherDeskPosition)>Aucun</option>
                        <option value="left" @selected($teacherDeskPosition === 'left')>Devant à gauche</option>
                        <option value="center" @selected($teacherDeskPosition === 'center')>Devant au milieu</option>
                        <option value="right" @selected($teacherDeskPosition === 'right')>Devant à droite</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Date effective de l'application</label>
                <x-filament::input.wrapper class="max-w-[10rem]">
                    <x-filament::input
                        type="date"
                        value="{{ $effectiveDate }}"
                        wire:change="updateEffectiveDate($event.target.value)"
                        wire:key="app-date-{{ $applicationId }}"
                    />
                </x-filament::input.wrapper>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-end gap-4">
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Type de case</label>
                <x-filament::input.wrapper class="max-w-[10rem]">
                    <x-filament::input.select wire:model="newCellType">
                        <option value="desk">Bureau</option>
                        <option value="blocked">Emplacement vide</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Capacité des nouvelles cases</label>
                <x-filament::input.wrapper class="max-w-[8rem]">
                    <x-filament::input.select wire:model="newDeskCapacity">
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <x-filament::button color="gray" wire:click="addRow">
                + Ligne
            </x-filament::button>

            <x-filament::button color="gray" wire:click="removeRow">
                − Ligne
            </x-filament::button>

            <x-filament::button color="gray" wire:click="addColumn">
                + Colonne
            </x-filament::button>

            <x-filament::button color="gray" wire:click="removeColumn">
                − Colonne
            </x-filament::button>
        </div>

        <div class="mt-4 border-t border-gray-100 pt-4 dark:border-white/5">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Critères du générateur</p>
            <div class="mt-2 flex flex-wrap items-center gap-4">
                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                    <input type="checkbox" wire:model="avoidSameSexNeighbors" class="fi-checkbox-input rounded border-gray-300 dark:border-gray-600" />
                    Alterner filles/garçons
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                    <input type="checkbox" wire:model="avoidRepeatSeats" class="fi-checkbox-input rounded border-gray-300 dark:border-gray-600" />
                    Éviter les places déjà occupées
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                    <input type="checkbox" wire:model="avoidRepeatNeighbors" class="fi-checkbox-input rounded border-gray-300 dark:border-gray-600" />
                    Éviter les voisins déjà eus
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                    <input type="checkbox" wire:model="heightOrdering" class="fi-checkbox-input rounded border-gray-300 dark:border-gray-600" />
                    Ordonner par taille (petits devant)
                </label>
                @if ($heightOrdering)
                    <div class="flex items-center gap-2">
                        <label class="text-sm text-gray-600 dark:text-gray-400">Marge acceptable (cm)</label>
                        <x-filament::input.wrapper class="w-20">
                            <x-filament::input type="number" min="0" wire:model="heightMarginCm" />
                        </x-filament::input.wrapper>
                    </div>
                @endif
            </div>
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">"Éviter les places/voisins déjà eus" ne compare qu'aux autres applications de ce même plan.</p>

            <div class="mt-4 flex flex-wrap gap-3">
                <x-filament::button wire:click="randomize">
                    Répartir aléatoirement
                </x-filament::button>

                <x-filament::button color="gray" wire:click="clearSeats">
                    Réinitialiser
                </x-filament::button>
            </div>
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
            <x-slot name="heading">Salle de classe</x-slot>
            <x-slot name="description">Cliquez sur un élève puis sur un siège pour le placer ou l'échanger. Cliquez sur une case vide pour y ajouter une table. Le cadenas verrouille un élève à sa place lors d'une répartition aléatoire ; l'icône 🚫 bloque une place vide pour cette application.</x-slot>

            @php($grid = $this->gridSize)
            @php($deskMap = $this->deskMap)
            @php($violations = $this->violations)

            {{-- inline-flex + flex-col makes this wrapper shrink to the width
                 of its widest child (the grid rows below), so the teacher's
                 desk aligns relative to the actual desk grid, not the full
                 panel width. --}}
            <div class="inline-flex flex-col">
                @if ($teacherDeskPosition)
                    <div @class([
                        'mb-3 flex',
                        'justify-start' => $teacherDeskPosition === 'left',
                        'justify-center' => $teacherDeskPosition === 'center',
                        'justify-end' => $teacherDeskPosition === 'right',
                    ])>
                        <div class="flex h-12 w-[15.5rem] items-center justify-center rounded-lg border-2 border-gray-300 bg-gray-100 text-xs font-medium text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                            Bureau du professeur
                        </div>
                    </div>
                @endif

                <div class="flex flex-col gap-3">
                    @for ($row = 0; $row < $grid['rows']; $row++)
                        <div class="flex flex-wrap gap-3">
                            @for ($col = 0; $col < $grid['cols']; $col++)
                                @php($desk = $deskMap->get($row.'-'.$col))

                                @if ($desk && $desk->is_blocked)
                                    <div
                                        class="flex items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 text-gray-400 dark:border-white/10 dark:bg-white/5 dark:text-gray-600"
                                        style="width: {{ $desk->capacity * 80 + ($desk->capacity - 1) * 4 + 16 }}px; height: 5.5rem;"
                                        wire:key="desk-{{ $desk->id }}"
                                    >
                                        <span class="text-xs">Emplacement vide</span>
                                        <button
                                            type="button"
                                            wire:click="removeDesk({{ $desk->id }})"
                                            class="ml-2 text-xs text-danger-500 hover:text-danger-600"
                                        >
                                            &times;
                                        </button>
                                    </div>
                                @elseif ($desk)
                                    <div class="rounded-lg border border-gray-200 p-2 dark:border-white/10" wire:key="desk-{{ $desk->id }}">
                                        <div class="mb-1 flex items-center justify-between gap-2">
                                            <button
                                                type="button"
                                                wire:click="toggleDeskCapacity({{ $desk->id }})"
                                                class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                            >
                                                {{ $desk->capacity }} places
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="removeDesk({{ $desk->id }})"
                                                class="text-xs text-danger-500 hover:text-danger-600"
                                            >
                                                &times;
                                            </button>
                                        </div>
                                        <div class="flex gap-1">
                                            @for ($seatIndex = 0; $seatIndex < $desk->capacity; $seatIndex++)
                                                @php($seat = $desk->seats->firstWhere('seat_index', $seatIndex))
                                                @php($occupant = $seat?->student)
                                                @php($isBlocked = $seat?->is_blocked ?? false)
                                                @php($occupantViolations = $occupant ? ($violations[$occupant->id] ?? []) : [])
                                                <div class="relative">
                                                    <button
                                                        type="button"
                                                        wire:click="seatClicked({{ $desk->id }}, {{ $seatIndex }})"
                                                        @if ($occupantViolations !== [])
                                                            title="{{ implode(' · ', $occupantViolations) }}"
                                                        @endif
                                                        @class([
                                                            'flex h-16 w-20 flex-col items-center justify-center rounded border p-1 text-center text-xs leading-tight',
                                                            'border-danger-500 bg-danger-50 ring-2 ring-danger-400 dark:bg-danger-500/10' => $occupantViolations !== [],
                                                            'border-primary-500 bg-primary-50 dark:bg-primary-500/10' => $occupantViolations === [] && $occupant && $selectedStudentId === $occupant->id,
                                                            'border-gray-200 bg-gray-50 hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10' => $occupantViolations === [] && $occupant && $selectedStudentId !== $occupant->id,
                                                            'border-dashed border-gray-300 bg-gray-100 text-gray-400 dark:border-white/10 dark:bg-white/10' => $isBlocked,
                                                            'border-dashed border-gray-300 text-gray-400 hover:border-gray-400 dark:border-white/10' => ! $occupant && ! $isBlocked,
                                                        ])
                                                    >
                                                        @if ($occupant)
                                                            <span @class(['font-medium', 'text-danger-700 dark:text-danger-300' => $occupantViolations !== [], 'text-gray-900 dark:text-white' => $occupantViolations === []])>{{ $occupant->first_name }}</span>
                                                            <span @class(['text-danger-600 dark:text-danger-400' => $occupantViolations !== [], 'text-gray-500 dark:text-gray-400' => $occupantViolations === []])>{{ $occupant->last_name }}</span>
                                                        @elseif ($isBlocked)
                                                            <span class="text-[10px]">Bloqué</span>
                                                        @else
                                                            — vide —
                                                        @endif
                                                    </button>

                                                    @if ($occupant)
                                                        <button
                                                            type="button"
                                                            wire:click="toggleSeatLock({{ $desk->id }}, {{ $seatIndex }})"
                                                            title="{{ $seat->is_locked ? 'Déverrouiller' : 'Verrouiller à cette place' }}"
                                                            @class([
                                                                'absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full border text-[10px]',
                                                                'border-warning-500 bg-warning-400 text-white' => $seat->is_locked,
                                                                'border-gray-300 bg-white text-gray-400 hover:text-gray-600 dark:border-white/10 dark:bg-gray-800' => ! $seat->is_locked,
                                                            ])
                                                        >
                                                            {{ $seat->is_locked ? '🔒' : '🔓' }}
                                                        </button>
                                                    @else
                                                        <button
                                                            type="button"
                                                            wire:click="toggleSeatBlock({{ $desk->id }}, {{ $seatIndex }})"
                                                            title="{{ $isBlocked ? 'Débloquer cette place' : 'Bloquer cette place (vide pour cette application)' }}"
                                                            @class([
                                                                'absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full border text-[10px]',
                                                                'border-gray-500 bg-gray-400 text-white' => $isBlocked,
                                                                'border-gray-300 bg-white text-gray-400 hover:text-gray-600 dark:border-white/10 dark:bg-gray-800' => ! $isBlocked,
                                                            ])
                                                        >
                                                            🚫
                                                        </button>
                                                    @endif

                                                    @if ($occupant?->seating_notes)
                                                        <p class="mt-1 w-20 text-[10px] leading-tight text-gray-500 dark:text-gray-400">{{ $occupant->seating_notes }}</p>
                                                    @endif
                                                </div>
                                            @endfor
                                        </div>
                                    </div>
                                @else
                                    <button
                                        type="button"
                                        wire:click="addDesk({{ $row }}, {{ $col }})"
                                        class="flex h-[5.5rem] w-24 items-center justify-center rounded-lg border-2 border-dashed border-gray-200 text-gray-300 hover:border-gray-300 hover:text-gray-400 dark:border-white/5 dark:text-white/10 dark:hover:border-white/10"
                                    >
                                        +
                                    </button>
                                @endif
                            @endfor
                        </div>
                    @endfor
                </div>
            </div>
        </x-filament::section>

        <x-filament::section class="mt-6">
            <x-slot name="heading">Élèves non placés ({{ $this->unassignedStudents->count() }})</x-slot>

            @if ($this->unassignedStudents->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">Tous les élèves sont placés.</p>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->unassignedStudents as $student)
                        <button
                            type="button"
                            wire:click="selectStudent({{ $student->id }})"
                            @class([
                                'rounded-full border px-3 py-1 text-sm',
                                'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300' => $selectedStudentId === $student->id,
                                'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10' => $selectedStudentId !== $student->id,
                            ])
                        >
                            {{ $student->first_name }} {{ $student->last_name }}
                        </button>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
