<x-filament-panels::page>
    <a href="{{ \App\Filament\Pages\Calendrier::getUrl() }}" class="text-sm text-primary-600 hover:underline dark:text-primary-400">
        ← Retour au calendrier
    </a>

    <x-filament::section class="mt-4">
        <x-slot name="heading">Lien d'abonnement</x-slot>
        <x-slot name="description">Ajoutez cette adresse dans votre application calendrier (Apple Calendar, Google Calendar...) comme "nouvel abonnement". Le flux est en lecture seule et se met à jour à chaque consultation.</x-slot>

        <div class="flex flex-wrap items-end gap-4">
            <div class="min-w-0 flex-1">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Adresse (https)</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" readonly value="{{ $this->feedUrl }}" onclick="this.select()" />
                </x-filament::input.wrapper>
            </div>

            <div class="min-w-0 flex-1">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Adresse (webcal)</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" readonly value="{{ $this->webcalUrl }}" onclick="this.select()" />
                </x-filament::input.wrapper>
            </div>

            <x-filament::button
                color="danger"
                wire:click="regenerateToken"
                onclick="confirm('Régénérer le lien invalidera l&#39;ancien. Vous devrez mettre à jour votre abonnement partout où il est utilisé. Continuer ?') || event.stopImmediatePropagation()"
            >
                Régénérer le lien
            </x-filament::button>
        </div>
    </x-filament::section>

    <x-filament::section class="mt-6">
        <x-slot name="heading">École-Directe</x-slot>
        <x-slot name="description">Renseignez l'adresse de votre flux École-Directe (le même que vous utilisez déjà pour vous y abonner) pour pouvoir associer une séance du cahier de texte à son créneau horaire officiel.</x-slot>

        <div class="flex flex-wrap items-end gap-4">
            <div class="min-w-0 flex-1">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Adresse du flux ICS École-Directe</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="url" wire:model="ecoleDirecteIcsUrl" placeholder="https://..." />
                </x-filament::input.wrapper>
                @error('ecoleDirecteIcsUrl') <p class="text-xs text-danger-500">{{ $message }}</p> @enderror
            </div>

            <x-filament::button color="gray" wire:click="saveEcoleDirecteUrl">
                Enregistrer
            </x-filament::button>

            <x-filament::button color="gray" wire:click="refreshEcoleDirecte" wire:loading.attr="disabled" wire:target="refreshEcoleDirecte">
                Rafraîchir
            </x-filament::button>
        </div>

        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            @if (auth()->user()->ecole_directe_synced_at)
                Dernière synchronisation : {{ auth()->user()->ecole_directe_synced_at->format('d/m/Y H:i') }}
            @else
                Jamais synchronisé.
            @endif
        </p>
    </x-filament::section>

    <x-filament::section class="mt-6">
        <x-slot name="heading">Catégories affichées sur le calendrier</x-slot>
        <x-slot name="description">Décochez ce que vous ne voulez pas voir sur le calendrier de l'application (page "Calendrier"). Sans effet sur le flux d'abonnement externe, réglé séparément ci-dessous.</x-slot>

        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            @foreach ($this->categoryLabels as $key => $label)
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <x-filament::input.checkbox wire:model="displayCategories" value="{{ $key }}" />
                    {{ $label }}
                </label>
            @endforeach
        </div>

        <x-filament::button class="mt-4" color="gray" wire:click="saveDisplayCategories">
            Enregistrer les catégories
        </x-filament::button>
    </x-filament::section>

    <x-filament::section class="mt-6">
        <x-slot name="heading">Catégories affichées dans le flux</x-slot>
        <x-slot name="description">Décochez ce dont vous n'avez pas besoin dans votre abonnement calendrier externe (Apple Calendar, Google Calendar...). Sans effet sur l'affichage dans l'application, réglé séparément ci-dessus.</x-slot>

        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            @foreach ($this->categoryLabels as $key => $label)
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <x-filament::input.checkbox wire:model="categories" value="{{ $key }}" />
                    {{ $label }}
                </label>
            @endforeach
        </div>

        <x-filament::button class="mt-4" color="gray" wire:click="saveCategories">
            Enregistrer les catégories
        </x-filament::button>
    </x-filament::section>

    <x-filament::section class="mt-6">
        <x-slot name="heading">Réunions, rendez-vous, vacances & événements de l'établissement</x-slot>
        <x-slot name="description">La gestion de ces événements a déménagé dans son propre écran, avec compte rendu au format markdown et pièces jointes.</x-slot>

        <x-filament::button tag="a" href="{{ \App\Filament\Resources\CalendarEvents\CalendarEventResource::getUrl() }}" icon="heroicon-o-arrow-top-right-on-square">
            Ouvrir « Réunions & événements »
        </x-filament::button>
    </x-filament::section>

    <x-filament::section class="mt-6">
        <div class="flex items-center justify-between gap-4">
            <x-slot name="heading">Anniversaires personnels</x-slot>

            @unless ($showBirthdayForm)
                <x-filament::button size="sm" color="gray" wire:click="addBirthday">
                    + Nouvel anniversaire
                </x-filament::button>
            @endunless
        </div>

        @php($birthdays = $this->personalBirthdays)

        @if ($birthdays->isEmpty() && ! $showBirthdayForm)
            <p class="text-sm text-gray-500 dark:text-gray-400">Aucun anniversaire personnel pour le moment.</p>
        @else
            <div class="mt-2 divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($birthdays as $birthday)
                    <div class="flex flex-wrap items-center justify-between gap-2 py-3" wire:key="birthday-{{ $birthday->id }}">
                        <div>
                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $birthday->name }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $birthday->date->format('d/m/Y') }}</div>
                        </div>

                        <div class="flex items-center gap-2">
                            <x-filament::button size="xs" color="gray" wire:click="editBirthday({{ $birthday->id }})">
                                Modifier
                            </x-filament::button>
                            <x-filament::button
                                size="xs"
                                color="danger"
                                wire:click="deleteBirthday({{ $birthday->id }})"
                                onclick="confirm('Supprimer cet anniversaire ?') || event.stopImmediatePropagation()"
                            >
                                Supprimer
                            </x-filament::button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($showBirthdayForm)
            <div class="mt-4 rounded-lg border border-gray-200 p-4 dark:border-white/10">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="min-w-0 flex-1">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Nom</label>
                        <x-filament::input.wrapper>
                            <x-filament::input type="text" wire:model="birthdayName" />
                        </x-filament::input.wrapper>
                        @error('birthdayName') <p class="text-xs text-danger-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Date de naissance</label>
                        <x-filament::input.wrapper>
                            <x-filament::input type="date" wire:model="birthdayDate" />
                        </x-filament::input.wrapper>
                        @error('birthdayDate') <p class="text-xs text-danger-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-4 flex gap-2">
                    <x-filament::button wire:click="saveBirthday">
                        Enregistrer
                    </x-filament::button>
                    <x-filament::button color="gray" wire:click="cancelBirthdayForm">
                        Annuler
                    </x-filament::button>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
