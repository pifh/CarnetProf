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

            <x-filament::button tag="a" href="{{ \App\Filament\Resources\ProgressionSequences\ProgressionSequenceResource::getUrl('index') }}" color="gray">
                Gérer les séquences
            </x-filament::button>
        </div>
    </x-filament::section>

    @php($sequences = $this->sequences)

    @if ($sequences->isEmpty())
        <x-filament::section class="mt-6">
            <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                Aucune séquence pour cette classe. Ajoutez votre première séquence de progression.
            </div>
        </x-filament::section>
    @else
        <x-filament::section class="mt-6">
            <div class="flex flex-wrap items-center gap-6">
                <div class="flex-1">
                    <div class="mb-1 flex items-center justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Programme réalisé</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ $this->progressPercent }}%</span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                        <div class="h-full rounded-full bg-primary-500" style="width: {{ $this->progressPercent }}%"></div>
                    </div>
                </div>

                @if ($this->pacing['label'])
                    <x-filament::badge :color="$this->pacing['color']">
                        {{ $this->pacing['label'] }}
                    </x-filament::badge>
                @endif
            </div>
        </x-filament::section>

        <x-filament::section class="mt-6">
            <x-slot name="heading">Séquences</x-slot>
            <x-slot name="description">Cliquez sur une séquence pour faire avancer son statut (à faire → en cours → terminée).</x-slot>

            <div class="space-y-2">
                @foreach ($sequences as $sequence)
                    @php($statusMeta = [
                        'not_started' => ['label' => 'À faire', 'color' => 'gray'],
                        'in_progress' => ['label' => 'En cours', 'color' => 'warning'],
                        'done' => ['label' => 'Terminée', 'color' => 'success'],
                    ][$sequence->status])

                    <button
                        type="button"
                        wire:click="toggleStatus({{ $sequence->id }})"
                        wire:key="sequence-{{ $sequence->id }}"
                        class="flex w-full items-center justify-between gap-4 rounded-lg border border-gray-200 p-3 text-left hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5"
                    >
                        <div>
                            <div class="font-medium text-gray-900 dark:text-white">{{ $sequence->title }}</div>
                            @if ($sequence->term)
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $sequence->term->label }}</div>
                            @endif
                            @if ($sequence->description)
                                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $sequence->description }}</div>
                            @endif
                        </div>

                        <x-filament::badge :color="$statusMeta['color']">
                            {{ $statusMeta['label'] }}
                        </x-filament::badge>
                    </button>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
