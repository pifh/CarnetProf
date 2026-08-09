<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Version actuellement déployée</x-slot>

        @if ($currentHash)
            <p class="text-sm text-gray-600 dark:text-gray-400">
                <code class="font-mono">{{ $currentHash }}</code> — {{ $currentSummary }}
                <span class="text-gray-400 dark:text-gray-500">({{ $currentDate }})</span>
            </p>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Version inconnue (le dossier n'est pas un dépôt git).</p>
        @endif
    </x-filament::section>

    <x-filament::section class="mt-6">
        <x-slot name="heading">Vérifier les mises à jour</x-slot>

        <div class="space-y-4">
            <x-filament::button wire:click="checkForUpdates" wire:loading.attr="disabled" wire:target="checkForUpdates">
                Vérifier les mises à jour
            </x-filament::button>

            @if ($hasChecked)
                @if ($checkError)
                    <p class="text-sm text-danger-600 dark:text-danger-400">{{ $checkError }}</p>
                @elseif ($behindBy === 0)
                    <p class="text-sm text-success-600 dark:text-success-400">Le site est à jour.</p>
                @else
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                            {{ $behindBy }} commit(s) en attente sur GitHub :
                        </p>
                        <ul class="text-sm font-mono space-y-1 mb-4 text-gray-600 dark:text-gray-400">
                            @foreach ($pendingCommits as $commit)
                                <li>{{ $commit }}</li>
                            @endforeach
                        </ul>

                        <x-filament::button
                            color="danger"
                            wire:click="runUpdate"
                            wire:loading.attr="disabled"
                            wire:target="runUpdate"
                            onclick="confirm('Le site passera en mode maintenance le temps de la mise à jour (git pull, composer, build, migrations). Les professeurs connectés seront temporairement interrompus. Continuer ?') || event.stopImmediatePropagation()"
                        >
                            <span wire:loading.remove wire:target="runUpdate">Mettre à jour maintenant</span>
                            <span wire:loading wire:target="runUpdate">Mise à jour en cours…</span>
                        </x-filament::button>
                    </div>
                @endif
            @endif
        </div>
    </x-filament::section>

    @if ($lastUpdateOutput)
        <x-filament::section class="mt-6">
            <x-slot name="heading">Journal de la dernière mise à jour</x-slot>

            <p class="text-sm mb-2 {{ $lastUpdateSuccessful ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                {{ $lastUpdateSuccessful ? 'Terminée avec succès.' : 'Échec — le site est peut-être resté en mode maintenance.' }}
            </p>
            <pre class="text-xs overflow-x-auto bg-gray-950 text-gray-100 rounded-lg p-4 whitespace-pre-wrap">{{ $lastUpdateOutput }}</pre>
        </x-filament::section>
    @endif
</x-filament-panels::page>
