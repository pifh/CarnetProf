<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Une sauvegarde de la base de données est effectuée automatiquement chaque nuit.
                Vous pouvez aussi en déclencher une manuellement ci-dessous.
            </p>

            <x-filament::button wire:click="runBackup" wire:loading.attr="disabled" wire:target="runBackup">
                Sauvegarder maintenant
            </x-filament::button>
        </div>
    </x-filament::section>

    <x-filament::section class="mt-6">
        <x-slot name="heading">Sauvegardes existantes</x-slot>

        @php($backups = $this->backups)

        @if ($backups->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">Aucune sauvegarde pour le moment.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left">
                            <th class="p-2">Date</th>
                            <th class="p-2">Taille</th>
                            <th class="p-2">Disque</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($backups as $backup)
                            <tr class="border-t border-gray-100 dark:border-white/5">
                                <td class="p-2">{{ $backup['date']->format('d/m/Y H:i') }}</td>
                                <td class="p-2">{{ $backup['size'] }}</td>
                                <td class="p-2">{{ $backup['disk'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
