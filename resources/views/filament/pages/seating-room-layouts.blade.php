<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Disposition</label>
                <x-filament::input.wrapper class="max-w-sm">
                    <x-filament::input.select wire:model.live="planId">
                        @foreach ($this->plans as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <x-filament::button color="gray" wire:click="createPlan">
                Nouvelle disposition
            </x-filament::button>

            <x-filament::button color="gray" wire:click="duplicatePlan">
                Dupliquer cette disposition
            </x-filament::button>

            <x-filament::button
                color="danger"
                wire:click="deletePlan"
                onclick="confirm('Supprimer cette disposition et tous les plans de classe qui l&#39;utilisent ?') || event.stopImmediatePropagation()"
            >
                Supprimer cette disposition
            </x-filament::button>
        </div>

        <div class="mt-4 flex flex-wrap items-end gap-4">
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Nom</label>
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
    </x-filament::section>

    <x-filament::section class="mt-6">
        <x-slot name="heading">Salle</x-slot>
        <x-slot name="description">Cliquez sur une case vide pour y ajouter une table. Cette disposition est partagée par tous les plans de classe qui l'utilisent, quelle que soit la classe ou la date.</x-slot>

        @php($grid = $this->gridSize)
        @php($deskMap = $this->deskMap)

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
                                            <div class="flex h-16 w-20 flex-col items-center justify-center rounded border border-dashed border-gray-200 text-center text-xs text-gray-300 dark:border-white/10 dark:text-white/20">
                                                place
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
</x-filament-panels::page>
