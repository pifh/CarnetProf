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
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Plan</label>
                <x-filament::input.wrapper class="max-w-sm">
                    <x-filament::input.select wire:model.live="planId">
                        @foreach ($this->plans as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <x-filament::button color="gray" wire:click="createPlan">
                Nouveau plan
            </x-filament::button>

            <x-filament::button color="gray" wire:click="duplicatePlan">
                Dupliquer
            </x-filament::button>

            <x-filament::button
                color="danger"
                wire:click="deletePlan"
                onclick="confirm('Supprimer ce plan et ses placements ?') || event.stopImmediatePropagation()"
            >
                Supprimer ce plan
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
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Capacité des nouvelles tables</label>
                <x-filament::input.wrapper class="max-w-[8rem]">
                    <x-filament::input.select wire:model="newDeskCapacity">
                        <option value="2">2</option>
                        <option value="3">3</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <x-filament::button color="gray" wire:click="addRow">
                + Ligne
            </x-filament::button>

            <x-filament::button color="gray" wire:click="addColumn">
                + Colonne
            </x-filament::button>

            <x-filament::button wire:click="randomize">
                Répartir aléatoirement
            </x-filament::button>

            <x-filament::button color="gray" wire:click="clearSeats">
                Réinitialiser
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
            <x-slot name="heading">Salle de classe</x-slot>
            <x-slot name="description">Cliquez sur un élève puis sur un siège pour le placer ou l'échanger. Cliquez sur une case vide pour y ajouter une table.</x-slot>

            @php($grid = $this->gridSize)
            @php($deskMap = $this->deskMap)

            <div class="flex flex-col gap-3">
                @for ($row = 0; $row < $grid['rows']; $row++)
                    <div class="flex flex-wrap gap-3">
                        @for ($col = 0; $col < $grid['cols']; $col++)
                            @php($desk = $deskMap->get($row.'-'.$col))

                            @if ($desk)
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
                                            <div>
                                                <button
                                                    type="button"
                                                    wire:click="seatClicked({{ $desk->id }}, {{ $seatIndex }})"
                                                    @class([
                                                        'flex h-16 w-20 flex-col items-center justify-center rounded border p-1 text-center text-xs leading-tight',
                                                        'border-primary-500 bg-primary-50 dark:bg-primary-500/10' => $occupant && $selectedStudentId === $occupant->id,
                                                        'border-gray-200 bg-gray-50 hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10' => ! ($occupant && $selectedStudentId === $occupant->id) && $occupant,
                                                        'border-dashed border-gray-300 text-gray-400 hover:border-gray-400 dark:border-white/10' => ! $occupant,
                                                    ])
                                                >
                                                    @if ($occupant)
                                                        <span class="font-medium text-gray-900 dark:text-white">{{ $occupant->first_name }}</span>
                                                        <span class="text-gray-500 dark:text-gray-400">{{ $occupant->last_name }}</span>
                                                    @else
                                                        — vide —
                                                    @endif
                                                </button>
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
