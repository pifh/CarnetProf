<div class="space-y-4">
    @if (! $this->student)
        <x-filament::section>
            <div class="py-4 text-center text-gray-500 dark:text-gray-400">Élève introuvable.</div>
        </x-filament::section>
    @else
        {{-- En-tête: photo, nom, classe, besoins particuliers, période --}}
        <x-filament::section>
            <div class="flex flex-wrap items-start gap-4">
                <div class="h-20 w-20 shrink-0 overflow-hidden rounded-lg">
                    @include('filament.pages.partials.student-photo', ['student' => $this->student, 'clickable' => false])
                </div>

                <div class="min-w-[12rem] flex-1">
                    <div class="text-lg font-semibold text-gray-950 dark:text-white">
                        {{ $this->student->first_name }} {{ $this->student->last_name }}
                    </div>

                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        @if ($allowClassSwitch && $this->classOptions->count() > 1)
                            <x-filament::input.wrapper class="max-w-[12rem]">
                                <x-filament::input.select wire:model.live="schoolClassId">
                                    @foreach ($this->classOptions as $option)
                                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        @elseif ($this->schoolClass)
                            <x-filament::badge color="gray">{{ $this->schoolClass->name }}</x-filament::badge>
                        @endif

                        @if ($this->student->special_needs)
                            @foreach ($this->student->special_needs as $tag)
                                <x-filament::badge color="warning">{{ $tag }}</x-filament::badge>
                            @endforeach
                        @endif
                    </div>
                </div>

                <div>
                    <label class="text-xs font-medium text-gray-700 dark:text-gray-300">Période</label>
                    <x-filament::input.wrapper class="max-w-[12rem]">
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

        {{-- Moyennes --}}
        <x-filament::section heading="Moyennes">

            @if ($this->subjectAverages->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">Aucune classe sélectionnée.</p>
            @else
                <ul class="divide-y divide-gray-100 text-sm dark:divide-white/5">
                    @foreach ($this->subjectAverages as $row)
                        <li class="py-1.5">
                            <div class="flex items-center justify-between">
                                <span class="{{ $row['subject'] === null ? 'font-semibold' : '' }}">{{ $row['label'] }}</span>
                                <span>{{ $row['average'] !== null ? number_format($row['average'], 2).'/20' : '—' }}</span>
                            </div>
                            @if ($row['grades']->isNotEmpty())
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @foreach ($row['grades'] as $grade)
                                        <x-filament::badge
                                            size="sm"
                                            color="gray"
                                            :tooltip="$grade->evaluation->title.' · coef. '.number_format((float) $grade->evaluation->coefficient, 2)"
                                        >
                                            {{ number_format((float) $grade->score, 2) }}/{{ number_format((float) $grade->evaluation->max_score, 2) }}
                                        </x-filament::badge>
                                    @endforeach
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            <x-filament::link :href="$this->averagesPageUrl" target="_blank" class="mt-2 inline-block text-xs">
                Voir les bulletins PDF →
            </x-filament::link>
        </x-filament::section>

        {{-- Oublis & discipline --}}
        <x-filament::section heading="Oublis & discipline">

            <div class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                @foreach (\App\Support\DisciplineCategories::labels() as $category => $label)
                    @php($counts = $this->disciplineCounts[$category] ?? ['total' => 0, 'trip' => 0])
                    @php($dates = $this->disciplineEntries->get($category, collect()))
                    <div class="rounded-lg border border-gray-200 p-2 dark:border-white/10">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</span>
                            <span class="font-semibold">
                                {{ $counts['trip'] }}
                                <span class="text-xs font-normal text-gray-400">({{ $counts['total'] }} total)</span>
                            </span>
                        </div>
                        @if ($dates->isNotEmpty())
                            <div class="mt-1 flex flex-wrap gap-1">
                                @foreach ($dates as $entry)
                                    <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600 dark:bg-white/10 dark:text-gray-300">
                                        {{ $entry->occurred_at->format('d/m/Y') }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        {{-- Appréciation --}}
        <x-filament::section heading="Appréciation">

            @if ($this->subjects->count() > 1)
                <div class="mb-3">
                    <label class="text-xs font-medium text-gray-700 dark:text-gray-300">Matière</label>
                    <x-filament::input.wrapper class="max-w-[12rem]">
                        <x-filament::input.select wire:model.live="subjectId">
                            @foreach ($this->subjects as $subject)
                                <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            @endif

            <div wire:key="appreciation-{{ $termId }}-{{ $subjectId }}">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium">{{ $termId ? 'Cette période' : 'Année complète' }}</span>
                    @if ($appreciationReadOnly)
                        <x-filament::badge :color="($this->appreciation?->is_draft ?? true) ? 'warning' : 'success'">
                            {{ ($this->appreciation?->is_draft ?? true) ? 'Brouillon' : 'Finalisée' }}
                        </x-filament::badge>
                    @else
                        <x-filament::button
                            size="xs"
                            :color="($this->appreciation?->is_draft ?? true) ? 'warning' : 'success'"
                            :disabled="! $this->appreciation"
                            wire:click="toggleAppreciationDraft"
                        >
                            {{ ($this->appreciation?->is_draft ?? true) ? 'Brouillon' : 'Finalisée' }}
                        </x-filament::button>
                    @endif
                </div>
                @if ($appreciationReadOnly)
                    <p class="mt-1 text-sm {{ $this->appreciation?->content ? '' : 'text-gray-500 dark:text-gray-400 italic' }}">
                        {{ $this->appreciation?->content ?: 'Aucune appréciation rédigée.' }}
                    </p>
                @else
                    <x-filament::input.wrapper class="mt-1">
                        <textarea
                            rows="3"
                            class="fi-input block w-full border-none bg-transparent p-0 text-sm focus:ring-0"
                            wire:change="updateAppreciation($event.target.value)"
                        >{{ $this->appreciation?->content }}</textarea>
                    </x-filament::input.wrapper>
                @endif
            </div>
        </x-filament::section>

        {{-- Historique des événements --}}
        <x-filament::section heading="Historique des événements">

            @if ($this->recentEvents->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">Aucun événement enregistré.</p>
            @else
                <ul class="space-y-2 text-sm">
                    @foreach ($this->recentEvents as $event)
                        <li
                            class="flex cursor-pointer items-center justify-between gap-2 rounded border-b border-gray-100 p-1.5 pb-2 last:border-0 hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5"
                            wire:click="$dispatch('open-student-event-detail', { eventId: {{ $event->id }} })"
                        >
                            <div>
                                <x-filament::badge size="sm">{{ $event->type }}</x-filament::badge>
                                <span class="ml-1 text-gray-500 dark:text-gray-400">{{ $event->starts_at->format('d/m/Y') }}</span>
                            </div>
                            @if ($event->notes)
                                <span class="truncate text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::limit(strip_tags($event->notes), 40) }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            <x-filament::link :href="$this->eventsIndexUrl" target="_blank" class="mt-2 inline-block text-xs">
                Voir tous les événements →
            </x-filament::link>
        </x-filament::section>
    @endif
</div>
