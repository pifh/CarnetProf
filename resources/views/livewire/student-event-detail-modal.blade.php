<div>
    @if ($this->event)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4"
            wire:click.self="close"
            wire:keydown.escape.window="close"
        >
            <div class="w-full max-w-lg rounded-xl bg-white p-8 shadow-xl dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ $this->event->student->first_name }} {{ $this->event->student->last_name }}
                    </span>
                    <button
                        type="button"
                        wire:click="close"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                        aria-label="Fermer"
                    >
                        &times;
                    </button>
                </div>

                <div class="mt-3 flex items-center gap-2">
                    <x-filament::badge>{{ $this->event->type }}</x-filament::badge>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $this->event->starts_at->format('d/m/Y'.($this->event->all_day ? '' : ' H:i')) }}
                        @if ($this->event->ends_at)
                            → {{ $this->event->ends_at->format('d/m/Y'.($this->event->all_day ? '' : ' H:i')) }}
                        @endif
                    </span>
                </div>

                @if ($this->event->notes)
                    <div class="prose prose-sm mt-4 max-w-none dark:prose-invert">
                        {!! str($this->event->notes)->markdown() !!}
                    </div>
                @endif

                @if ($this->event->attachments->isNotEmpty())
                    <div class="mt-4">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Pièces jointes</div>
                        <ul class="mt-1 space-y-1">
                            @foreach ($this->event->attachments as $attachment)
                                <li>
                                    <a
                                        href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($attachment->path) }}"
                                        target="_blank"
                                        class="text-sm text-primary-600 hover:underline dark:text-primary-400"
                                    >
                                        {{ $attachment->original_filename }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <x-filament::button
                    tag="a"
                    href="{{ \App\Filament\Resources\StudentEvents\StudentEventResource::getUrl('edit', ['record' => $this->event]) }}"
                    color="primary"
                    class="mt-6 w-full justify-center"
                >
                    Modifier cet événement
                </x-filament::button>
            </div>
        </div>
    @endif
</div>
