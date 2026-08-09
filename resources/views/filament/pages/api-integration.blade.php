<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Points d'accès JSON</x-slot>
        <x-slot name="description">Ces adresses renvoient du JSON en lecture seule, sans connexion — utilisez-les depuis un workflow N8N (ou tout autre outil capable de faire une requête HTTP GET) pour récupérer les anniversaires du jour, le planning du jour et les actions en attente, par exemple pour une impression automatique chaque matin.</x-slot>

        <div class="space-y-4">
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Anniversaires du jour</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" readonly value="{{ $this->birthdaysUrl }}" onclick="this.select()" />
                </x-filament::input.wrapper>
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Planning du jour</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" readonly value="{{ $this->scheduleUrl }}" onclick="this.select()" />
                </x-filament::input.wrapper>
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Rappels personnels non faits</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" readonly value="{{ $this->remindersUrl }}" onclick="this.select()" />
                </x-filament::input.wrapper>
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Devoirs à rendre aujourd'hui</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" readonly value="{{ $this->homeworkUrl }}" onclick="this.select()" />
                </x-filament::input.wrapper>
            </div>
        </div>

        <x-filament::button
            color="danger"
            class="mt-6"
            wire:click="regenerateToken"
            onclick="confirm('Régénérer le jeton invalidera ces 4 adresses. Vous devrez mettre à jour vos flux N8N. Continuer ?') || event.stopImmediatePropagation()"
        >
            Régénérer le jeton
        </x-filament::button>
    </x-filament::section>
</x-filament-panels::page>
