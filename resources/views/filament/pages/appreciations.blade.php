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

            @if ($this->subjects->isNotEmpty())
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Matière</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="subjectId">
                            @foreach ($this->subjects as $subject)
                                <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            @endif

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Période</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="termId">
                        @foreach ($this->terms as $term)
                            <option value="{{ $term->id }}">{{ $term->label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="type">
                        <option value="general">Générale</option>
                        <option value="disciplinary">Disciplinaire</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section class="mt-6">
        @php($students = $this->students)

        @if ($students->isEmpty())
            <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                Aucun élève dans cette classe, ou aucune classe/période créée.
            </div>
        @else
            <div class="space-y-4">
                @foreach ($students as $student)
                    @php($appreciation = $student->appreciation)
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10" wire:key="student-{{ $student->id }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <x-student-name :student="$student" class="font-medium text-gray-900 dark:text-white" />

                            <div class="flex flex-wrap items-center gap-2">
                                @if ($this->templates->isNotEmpty())
                                    <x-filament::input.wrapper class="max-w-[14rem]">
                                        <x-filament::input.select
                                            wire:change="applyTemplate({{ $student->id }}, $event.target.value)"
                                        >
                                            <option value="">Insérer un modèle…</option>
                                            @foreach ($this->templates as $template)
                                                <option value="{{ $template->id }}">{{ $template->label }}</option>
                                            @endforeach
                                        </x-filament::input.select>
                                    </x-filament::input.wrapper>
                                @endif

                                @if ($type === 'general')
                                    <x-filament::button size="xs" color="gray" wire:click="suggest({{ $student->id }})">
                                        Suggérer
                                    </x-filament::button>
                                @endif

                                <x-filament::button
                                    size="xs"
                                    :color="($appreciation?->is_draft ?? true) ? 'warning' : 'success'"
                                    :disabled="! $appreciation"
                                    wire:click="toggleDraft({{ $student->id }})"
                                >
                                    {{ ($appreciation?->is_draft ?? true) ? 'Brouillon' : 'Finalisée' }}
                                </x-filament::button>
                            </div>
                        </div>

                        <x-filament::input.wrapper class="mt-2">
                            <textarea
                                rows="3"
                                class="fi-input block w-full border-none bg-transparent p-0 text-sm focus:ring-0"
                                wire:change="updateContent({{ $student->id }}, $event.target.value)"
                            >{{ $appreciation?->content }}</textarea>
                        </x-filament::input.wrapper>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
