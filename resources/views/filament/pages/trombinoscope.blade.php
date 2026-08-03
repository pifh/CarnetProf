<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-wrap items-end justify-between gap-4">
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

            @if ($schoolClassId)
                <div class="flex gap-2">
                    <x-filament::button tag="a" href="{{ \App\Filament\Pages\ImportClassPhotos::getUrl(['schoolClass' => $schoolClassId]) }}" color="gray">
                        Importer depuis un PDF
                    </x-filament::button>
                    <x-filament::button color="gray" wire:click="downloadPdf">
                        Télécharger en PDF (A4)
                    </x-filament::button>
                </div>
            @endif
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
        <div class="mt-6 flex flex-wrap gap-4">
            @foreach ($students as $student)
                <div class="{{ $managingStudentId === $student->id ? 'w-64' : 'w-32' }} shrink-0 overflow-hidden rounded-lg border border-gray-200 dark:border-white/10" wire:key="student-{{ $student->id }}">
                    <div class="aspect-[2/3] w-32">
                        @include('filament.pages.partials.student-photo', ['student' => $student])
                    </div>
                    <div class="p-2 text-center">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $student->first_name }} {{ $student->last_name }}</div>
                        <x-filament::button size="xs" color="gray" class="mt-1" wire:click="toggleManaging({{ $student->id }})">
                            {{ $managingStudentId === $student->id ? 'Fermer' : 'Gérer' }}
                        </x-filament::button>
                    </div>

                    @if ($managingStudentId === $student->id)
                        <div class="border-t border-gray-200 p-3 dark:border-white/10">
                            <label class="text-xs font-medium text-gray-700 dark:text-gray-300">Nouvelle photo</label>
                            <input
                                type="file"
                                wire:model="newPhoto"
                                accept="image/*"
                                class="mt-1 block w-full text-xs text-gray-600 file:mr-2 file:rounded-md file:border-0 file:bg-primary-600 file:px-2 file:py-1 file:text-xs file:text-white dark:text-gray-400"
                            />
                            @error('newPhoto') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror

                            <x-filament::button size="xs" color="success" class="mt-2 w-full" wire:click="uploadPhoto">
                                Envoyer
                            </x-filament::button>

                            @php($history = $this->photoHistory)

                            @if ($history->isNotEmpty())
                                <div class="mt-3">
                                    <div class="text-xs font-medium text-gray-700 dark:text-gray-300">Historique</div>
                                    <div class="mt-1 space-y-2">
                                        @foreach ($history as $photo)
                                            <div class="flex items-center gap-2 rounded border border-gray-200 p-1 text-xs dark:border-white/10" wire:key="photo-{{ $photo->id }}">
                                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($photo->path) }}" class="h-12 w-8 rounded object-cover" alt="" />
                                                <div class="flex-1">
                                                    <div class="text-gray-700 dark:text-gray-300">{{ $photo->school_year ?? '—' }}</div>
                                                    <div class="text-gray-400">{{ $photo->created_at->format('d/m/Y') }}</div>
                                                </div>
                                                @if ($photo->is_current)
                                                    <x-filament::badge color="success" size="xs">Actuelle</x-filament::badge>
                                                @else
                                                    <div class="flex flex-col gap-1">
                                                        <x-filament::button size="xs" color="gray" wire:click="restorePhoto({{ $photo->id }})">
                                                            Restaurer
                                                        </x-filament::button>
                                                        <x-filament::button
                                                            size="xs"
                                                            color="danger"
                                                            wire:click="deletePhoto({{ $photo->id }})"
                                                            wire:confirm="Supprimer définitivement cette ancienne photo ?"
                                                        >
                                                            Supprimer
                                                        </x-filament::button>
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
