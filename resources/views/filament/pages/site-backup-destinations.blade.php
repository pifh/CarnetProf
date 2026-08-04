<x-filament-panels::page>
    <x-filament::section>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Ces destinations reçoivent la sauvegarde complète du site (base de données), en plus du disque local,
            à la fois lors de la sauvegarde planifiée chaque nuit et lors d'une sauvegarde manuelle depuis la page « Sauvegardes ».
        </p>
    </x-filament::section>

    <x-filament::section class="mt-6">
        <div class="flex items-center justify-between gap-4">
            <x-slot name="heading">Destinations du site</x-slot>

            @unless ($showForm)
                <x-filament::button size="sm" color="gray" wire:click="addDestination">
                    + Nouvelle destination
                </x-filament::button>
            @endunless
        </div>

        @php($destinations = $this->destinations)

        @if ($destinations->isEmpty() && ! $showForm)
            <p class="text-sm text-gray-500 dark:text-gray-400">Aucune destination configurée pour le moment.</p>
        @else
            <div class="mt-2 divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($destinations as $destination)
                    <div class="flex flex-wrap items-center justify-between gap-2 py-3" wire:key="destination-{{ $destination->id }}">
                        <div>
                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $destination->label }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $this->providers[$destination->provider] ?? $destination->provider }}
                                @unless ($destination->is_active)
                                    · <span class="text-danger-500">désactivée</span>
                                @endunless
                                @if ($destination->last_used_at)
                                    · dernière sauvegarde : {{ $destination->last_used_at->format('d/m/Y H:i') }}
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <x-filament::button size="xs" color="gray" wire:click="testConnection({{ $destination->id }})">
                                Tester
                            </x-filament::button>
                            <x-filament::button size="xs" color="gray" wire:click="toggleActive({{ $destination->id }})">
                                {{ $destination->is_active ? 'Désactiver' : 'Activer' }}
                            </x-filament::button>
                            <x-filament::button size="xs" color="gray" wire:click="editDestination({{ $destination->id }})">
                                Modifier
                            </x-filament::button>
                            <x-filament::button
                                size="xs"
                                color="danger"
                                wire:click="deleteDestination({{ $destination->id }})"
                                onclick="confirm('Supprimer cette destination ?') || event.stopImmediatePropagation()"
                            >
                                Supprimer
                            </x-filament::button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($showForm)
            <div class="mt-4 rounded-lg border border-gray-200 p-4 dark:border-white/10">
                <div class="flex flex-wrap items-end gap-4">
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Nom</label>
                        <x-filament::input.wrapper class="max-w-sm">
                            <x-filament::input type="text" wire:model="label" />
                        </x-filament::input.wrapper>
                        @error('label') <p class="text-xs text-danger-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
                        <x-filament::input.wrapper class="max-w-[14rem]">
                            <x-filament::input.select wire:model.live="provider" :disabled="(bool) $editingDestinationId">
                                @foreach ($this->providers as $key => $providerLabel)
                                    <option value="{{ $key }}">{{ $providerLabel }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-end gap-4">
                    @if (in_array($provider, ['ftp', 'sftp']))
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Hôte</label>
                            <x-filament::input.wrapper class="max-w-xs">
                                <x-filament::input type="text" wire:model="credentials.host" />
                            </x-filament::input.wrapper>
                            @error('credentials.host') <p class="text-xs text-danger-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Port</label>
                            <x-filament::input.wrapper class="max-w-[6rem]">
                                <x-filament::input type="number" wire:model="credentials.port" placeholder="{{ $provider === 'sftp' ? '22' : '21' }}" />
                            </x-filament::input.wrapper>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Identifiant</label>
                            <x-filament::input.wrapper class="max-w-xs">
                                <x-filament::input type="text" wire:model="credentials.username" />
                            </x-filament::input.wrapper>
                            @error('credentials.username') <p class="text-xs text-danger-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Mot de passe</label>
                            <x-filament::input.wrapper class="max-w-xs">
                                <x-filament::input type="password" wire:model="credentials.password" />
                            </x-filament::input.wrapper>
                        </div>

                        @if ($provider === 'sftp')
                            <div class="w-full">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Clé privée (optionnel, à la place du mot de passe)</label>
                                <x-filament::input.wrapper>
                                    <textarea
                                        rows="3"
                                        class="fi-input block w-full border-none bg-transparent p-0 text-sm focus:ring-0"
                                        wire:model="credentials.private_key"
                                    ></textarea>
                                </x-filament::input.wrapper>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Phrase secrète de la clé</label>
                                <x-filament::input.wrapper class="max-w-xs">
                                    <x-filament::input type="password" wire:model="credentials.passphrase" />
                                </x-filament::input.wrapper>
                            </div>
                        @endif

                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Répertoire distant</label>
                            <x-filament::input.wrapper class="max-w-xs">
                                <x-filament::input type="text" wire:model="credentials.root" placeholder="/" />
                            </x-filament::input.wrapper>
                        </div>
                    @elseif ($provider === 's3')
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Clé d'accès</label>
                            <x-filament::input.wrapper class="max-w-xs">
                                <x-filament::input type="text" wire:model="credentials.key" />
                            </x-filament::input.wrapper>
                            @error('credentials.key') <p class="text-xs text-danger-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Clé secrète</label>
                            <x-filament::input.wrapper class="max-w-xs">
                                <x-filament::input type="password" wire:model="credentials.secret" />
                            </x-filament::input.wrapper>
                            @error('credentials.secret') <p class="text-xs text-danger-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Bucket</label>
                            <x-filament::input.wrapper class="max-w-xs">
                                <x-filament::input type="text" wire:model="credentials.bucket" />
                            </x-filament::input.wrapper>
                            @error('credentials.bucket') <p class="text-xs text-danger-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Région</label>
                            <x-filament::input.wrapper class="max-w-[8rem]">
                                <x-filament::input type="text" wire:model="credentials.region" placeholder="auto" />
                            </x-filament::input.wrapper>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Point de terminaison (endpoint)</label>
                            <x-filament::input.wrapper class="max-w-xs">
                                <x-filament::input type="text" wire:model="credentials.endpoint" placeholder="https://..." />
                            </x-filament::input.wrapper>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Répertoire</label>
                            <x-filament::input.wrapper class="max-w-xs">
                                <x-filament::input type="text" wire:model="credentials.root" />
                            </x-filament::input.wrapper>
                        </div>

                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <x-filament::input.checkbox wire:model="credentials.use_path_style" />
                            Style de chemin (path-style, requis par la plupart des fournisseurs compatibles S3)
                        </label>
                    @elseif ($provider === 'webdav')
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Adresse (URL WebDAV)</label>
                            <x-filament::input.wrapper class="max-w-sm">
                                <x-filament::input type="text" wire:model="credentials.base_uri" placeholder="https://cloud.exemple.fr/remote.php/dav/files/site/" />
                            </x-filament::input.wrapper>
                            @error('credentials.base_uri') <p class="text-xs text-danger-500">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Identifiant</label>
                            <x-filament::input.wrapper class="max-w-xs">
                                <x-filament::input type="text" wire:model="credentials.username" />
                            </x-filament::input.wrapper>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Mot de passe</label>
                            <x-filament::input.wrapper class="max-w-xs">
                                <x-filament::input type="password" wire:model="credentials.password" />
                            </x-filament::input.wrapper>
                        </div>
                    @endif
                </div>

                <div class="mt-4 flex gap-2">
                    <x-filament::button wire:click="save">
                        Enregistrer
                    </x-filament::button>
                    <x-filament::button color="gray" wire:click="cancelForm">
                        Annuler
                    </x-filament::button>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
