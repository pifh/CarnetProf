<x-filament-panels::page>
    @if ($phase === 'upload')
        <form wire:submit.prevent="analyze">
            {{ $this->form }}
        </form>

        @if ($this->recentImports->isNotEmpty())
            <x-filament::section heading="Imports récents" class="mt-8">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left">
                                <th class="p-2">Date</th>
                                <th class="p-2">Fichier</th>
                                <th class="p-2">Importés</th>
                                <th class="p-2">Mis à jour</th>
                                <th class="p-2">Erreurs</th>
                                <th class="p-2">Statut</th>
                                <th class="p-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->recentImports as $import)
                                <tr class="border-t border-gray-100 dark:border-white/5">
                                    <td class="p-2">{{ $import->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="p-2">{{ $import->original_filename }}</td>
                                    <td class="p-2">{{ $import->imported_rows }}</td>
                                    <td class="p-2">{{ $import->duplicate_rows }}</td>
                                    <td class="p-2">{{ $import->error_rows }}</td>
                                    <td class="p-2">
                                        <x-filament::badge :color="$import->status === 'cancelled' ? 'gray' : 'success'">
                                            {{ $import->status === 'cancelled' ? 'Annulé' : 'Terminé' }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="p-2">
                                        @if ($import->isCancellable())
                                            <x-filament::button
                                                size="xs"
                                                color="danger"
                                                wire:click="cancelPastImport({{ $import->id }})"
                                                wire:confirm="Annuler cet import supprimera les élèves qu'il a créés. Continuer ?"
                                            >
                                                Annuler
                                            </x-filament::button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    @elseif ($phase === 'preview')
        @php($summary = $this->getPreviewSummary())
        <x-filament::section heading="Aperçu avant import">
            <div class="flex gap-3 mb-4">
                <x-filament::badge color="success">{{ $summary['valid'] }} à importer</x-filament::badge>
                <x-filament::badge color="info">{{ $summary['duplicates'] }} déjà existant(s) (seront mis à jour)</x-filament::badge>
                <x-filament::badge color="danger">{{ $summary['errors'] }} erreur(s)</x-filament::badge>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left">
                            <th class="p-2">Ligne</th>
                            <th class="p-2">Prénom</th>
                            <th class="p-2">Nom</th>
                            <th class="p-2">Classe</th>
                            <th class="p-2">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($previewRows as $row)
                            <tr class="border-t border-gray-100 dark:border-white/5">
                                <td class="p-2">{{ $row['row_number'] }}</td>
                                <td class="p-2">{{ $row['data']['first_name'] ?? '' }}</td>
                                <td class="p-2">{{ $row['data']['last_name'] ?? '' }}</td>
                                <td class="p-2">{{ $row['school_class_name'] }}</td>
                                <td class="p-2">
                                    @if (! empty($row['errors']))
                                        <x-filament::badge color="danger">{{ implode(', ', $row['errors']) }}</x-filament::badge>
                                    @elseif ($row['is_duplicate'])
                                        <x-filament::badge color="info">Déjà existant — sera mis à jour</x-filament::badge>
                                    @else
                                        <x-filament::badge color="success">OK</x-filament::badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex gap-2 mt-4">
                <x-filament::button wire:click="confirmImport" :disabled="$summary['valid'] === 0 && $summary['duplicates'] === 0">
                    Confirmer l'import
                    ({{ $summary['valid'] }} à créer, {{ $summary['duplicates'] }} à mettre à jour)
                </x-filament::button>
                <x-filament::button color="gray" wire:click="restart">
                    Recommencer
                </x-filament::button>
            </div>
        </x-filament::section>
    @elseif ($phase === 'report')
        <x-filament::section heading="Rapport d'import">
            <div class="flex gap-3 mb-4">
                <x-filament::badge color="success">{{ $completedImport->imported_rows }} importé(s)</x-filament::badge>
                <x-filament::badge color="info">{{ $completedImport->duplicate_rows }} mis à jour</x-filament::badge>
                <x-filament::badge color="danger">{{ $completedImport->error_rows }} erreur(s)</x-filament::badge>
            </div>

            @if ($completedImport->status === 'cancelled')
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Cet import a été annulé : les élèves qu'il avait créés ont été supprimés.</p>
            @else
                <div class="mb-4">
                    <x-filament::button
                        color="danger"
                        wire:click="cancelCompletedImport"
                        wire:confirm="Annuler cet import supprimera les élèves qu'il a créés. Continuer ?"
                    >
                        Annuler cet import
                    </x-filament::button>

                    @if ($completedImport->duplicate_rows > 0)
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Les {{ $completedImport->duplicate_rows }} fiche(s) déjà existante(s) mise(s) à jour par cet import ne seront pas remises dans leur état précédent par une annulation.</p>
                    @endif
                </div>
            @endif

            <x-filament::button color="gray" wire:click="restart">
                Nouvel import
            </x-filament::button>
        </x-filament::section>
    @endif
</x-filament-panels::page>
