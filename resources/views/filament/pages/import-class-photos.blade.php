<x-filament-panels::page>
    @if ($phase === 'upload')
        <x-filament::section>
            <x-slot name="heading">Importer un PDF de trombinoscope</x-slot>
            <x-slot name="description">
                Envoyez le PDF officiel de la classe « {{ $schoolClass->name }} ». Les photos qu'il contient seront extraites automatiquement,
                puis vous pourrez confirmer ou corriger le nom associé à chacune avant l'enregistrement.
            </x-slot>

            @if ($analysisError)
                <div class="mb-4 rounded-lg border border-warning-300 bg-warning-50 p-3 text-sm text-warning-700 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-300">
                    {{ $analysisError }}
                </div>
            @endif

            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Fichier PDF</label>
            <input
                type="file"
                wire:model="pdfFile"
                accept="application/pdf"
                class="mt-1 block w-full text-sm text-gray-600 file:mr-2 file:rounded-md file:border-0 file:bg-primary-600 file:px-3 file:py-2 file:text-sm file:text-white dark:text-gray-400"
            />
            @error('pdfFile') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror

            <div wire:loading wire:target="pdfFile,analyze" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Analyse en cours…
            </div>

            <x-filament::button class="mt-4" wire:click="analyze" wire:loading.attr="disabled" wire:target="analyze">
                Analyser
            </x-filament::button>
        </x-filament::section>
    @elseif ($phase === 'review')
        <x-filament::section>
            <x-slot name="heading">Associer chaque photo à un élève</x-slot>
            <x-slot name="description">
                Les photos sont pré-associées dans l'ordre du PDF avec les élèves triés par ordre alphabétique — corrigez si besoin, ou choisissez « Ignorer ».
            </x-slot>

            <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($this->candidates as $photo)
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10" wire:key="candidate-{{ $photo->id }}">
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($photo->path) }}" class="aspect-[2/3] w-full rounded object-cover" alt="" />

                        <x-filament::input.wrapper class="mt-2">
                            <x-filament::input.select wire:model="assignments.{{ $photo->id }}">
                                <option value="">Ignorer</option>
                                @foreach ($this->roster as $student)
                                    <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 flex gap-2">
                <x-filament::button color="success" wire:click="confirmImport">
                    Confirmer
                </x-filament::button>
                <x-filament::button color="gray" wire:click="restart" wire:confirm="Recommencer l'import ? Les associations en cours seront perdues.">
                    Recommencer
                </x-filament::button>
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">Import terminé</x-slot>

            <p class="text-sm text-gray-600 dark:text-gray-400">
                Les photos assignées ont été enregistrées pour la classe « {{ $schoolClass->name }} ».
            </p>

            <div class="mt-4 flex gap-2">
                <x-filament::button tag="a" href="{{ \App\Filament\Pages\Trombinoscope::getUrl(['schoolClassId' => $schoolClass->id]) }}" color="primary">
                    Voir le trombinoscope
                </x-filament::button>
                <x-filament::button color="gray" wire:click="restart">
                    Importer un autre PDF
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
