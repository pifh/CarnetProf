<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">En-tête Authorization (recommandé)</x-slot>
        <x-slot name="description">Si votre outil (N8N, etc.) permet d'ajouter un en-tête HTTP personnalisé, préférez cette forme : le jeton ne se retrouve pas dans l'URL (ni dans des journaux de serveur ou d'historique de navigateur). Ajoutez l'en-tête suivant à chaque requête :</x-slot>

        <div class="space-y-4">
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">En-tête Authorization</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" readonly value="Bearer {{ $this->token }}" onclick="this.select()" />
                </x-filament::input.wrapper>
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Anniversaires du jour</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" readonly value="{{ $this->birthdaysBearerUrl }}" onclick="this.select()" />
                </x-filament::input.wrapper>
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Planning du jour</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" readonly value="{{ $this->scheduleBearerUrl }}" onclick="this.select()" />
                </x-filament::input.wrapper>
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Rappels personnels non faits</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" readonly value="{{ $this->remindersBearerUrl }}" onclick="this.select()" />
                </x-filament::input.wrapper>
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Devoirs à rendre aujourd'hui</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" readonly value="{{ $this->homeworkBearerUrl }}" onclick="this.select()" />
                </x-filament::input.wrapper>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section class="mt-6">
        <x-slot name="heading">Jeton dans l'URL</x-slot>
        <x-slot name="description">Si votre outil ne permet pas d'ajouter un en-tête personnalisé, utilisez ces adresses à la place — le jeton fait partie de l'URL, chaque adresse suffit seule pour accéder aux données.</x-slot>

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
            onclick="confirm('Régénérer le jeton invalidera ces adresses et l’en-tête Authorization ci-dessus. Vous devrez mettre à jour vos flux N8N. Continuer ?') || event.stopImmediatePropagation()"
        >
            Régénérer le jeton
        </x-filament::button>
    </x-filament::section>
</x-filament-panels::page>
