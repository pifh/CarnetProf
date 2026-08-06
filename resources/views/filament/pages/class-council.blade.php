<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Classe</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="schoolClassId">
                        @foreach ($this->schoolClasses as $schoolClass)
                            <option value="{{ $schoolClass->id }}">{{ $schoolClass->name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Période</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="termId">
                        <option value="">Année complète</option>
                        @foreach ($this->terms as $term)
                            <option value="{{ $term->id }}">{{ $term->parent_id ? '— ' : '' }}{{ $term->label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>
    </x-filament::section>

    <div class="mt-6 flex flex-col gap-4 lg:flex-row">
        <div class="lg:w-64 lg:shrink-0">
            <x-filament::section>
                @if ($this->students->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">Aucun élève dans cette classe.</p>
                @else
                    <ul class="space-y-1">
                        @foreach ($this->students as $student)
                            <li>
                                <button
                                    type="button"
                                    wire:click="selectStudent({{ $student->id }})"
                                    @class([
                                        'w-full rounded-md px-2 py-1.5 text-left text-sm',
                                        'bg-primary-50 font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' => $selectedStudentId === $student->id,
                                        'hover:bg-gray-50 dark:hover:bg-white/5' => $selectedStudentId !== $student->id,
                                    ])
                                >
                                    {{ $student->first_name }} {{ $student->last_name }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-filament::section>
        </div>

        <div class="flex-1">
            @if ($selectedStudentId)
                @livewire('student-record-card', [
                    'studentId' => $selectedStudentId,
                    'schoolClassId' => $schoolClassId,
                    'defaultTermId' => $termId,
                    'allowClassSwitch' => false,
                ], key('council-fiche-'.$selectedStudentId.'-'.$schoolClassId.'-'.$termId))
            @else
                <x-filament::section>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Choisissez un élève dans la liste.</p>
                </x-filament::section>
            @endif
        </div>
    </div>
</x-filament-panels::page>
