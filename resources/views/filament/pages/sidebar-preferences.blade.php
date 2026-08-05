<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Glissez les éléments (ou utilisez les flèches) pour réordonner le menu de gauche, et désactivez ceux dont vous n'avez pas besoin.
    </p>

    <form wire:submit.prevent="save" class="mt-4">
        {{ $this->form }}

        <x-filament::button type="submit" class="mt-4">
            Enregistrer
        </x-filament::button>
    </form>
</x-filament-panels::page>
