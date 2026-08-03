<div>
    @if ($this->student)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4"
            wire:click.self="close"
            wire:keydown.escape.window="close"
        >
            <div class="w-full max-w-md rounded-xl bg-white p-8 shadow-xl dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Fiche élève</span>
                    <button
                        type="button"
                        wire:click="close"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                        aria-label="Fermer"
                    >
                        &times;
                    </button>
                </div>

                <div class="mt-4 flex flex-col items-center gap-4 text-center">
                    <div class="w-56 overflow-hidden rounded-xl">
                        @include('filament.pages.partials.student-photo', ['student' => $this->student, 'class' => 'aspect-[2/3] w-56 rounded-xl object-cover', 'clickable' => false])
                    </div>

                    <div>
                        <div class="text-xl font-semibold text-gray-900 dark:text-white">
                            {{ $this->student->first_name }} {{ $this->student->last_name }}
                        </div>
                        @if ($this->student->schoolClass)
                            <x-filament::badge color="gray" class="mt-1">
                                {{ $this->student->schoolClass->name }}
                            </x-filament::badge>
                        @endif
                    </div>
                </div>

                <x-filament::button
                    tag="a"
                    href="{{ \App\Filament\Resources\Students\StudentResource::getUrl('edit', ['record' => $this->student]) }}"
                    color="primary"
                    class="mt-6 w-full justify-center"
                >
                    Voir la fiche complète
                </x-filament::button>
            </div>
        </div>
    @endif
</div>
