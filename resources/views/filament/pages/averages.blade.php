<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Classe</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="schoolClassId">
                        @foreach ($this->schoolClasses as $schoolClass)
                            <option value="{{ $schoolClass->id }}">{{ $schoolClass->name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Période</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="termId">
                        <option value="">Année complète</option>
                        @foreach ($this->terms as $term)
                            <option value="{{ $term->id }}">{{ $term->label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 pb-2">
                <input type="checkbox" wire:model.live="showRanking" class="fi-checkbox-input rounded border-gray-300 dark:border-gray-600" />
                Afficher le classement
            </label>

            <x-filament::button color="gray" wire:click="exportCsv">
                Exporter CSV
            </x-filament::button>

            @if ($termId)
                <x-filament::button color="gray" wire:click="downloadClassBulletins">
                    Bulletins de la classe (PDF)
                </x-filament::button>
            @endif
        </div>
    </x-filament::section>

    <x-filament::section class="mt-6">
        @php($rows = $this->rows)

        @if ($rows->isEmpty())
            <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                Aucun élève dans cette classe, ou aucune classe créée.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left">
                            @if ($showRanking)
                                <th class="p-2">Rang</th>
                            @endif
                            <th class="p-2">Élève</th>
                            <th class="p-2">Moyenne</th>
                            @if ($termId)
                                <th class="p-2"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $index => $row)
                            <tr class="border-t border-gray-100 dark:border-white/5">
                                @if ($showRanking)
                                    <td class="p-2">{{ $row['average'] !== null ? $index + 1 : '—' }}</td>
                                @endif
                                <td class="p-2">{{ $row['student']->first_name }} {{ $row['student']->last_name }}</td>
                                <td class="p-2">
                                    {{ $row['average'] !== null ? number_format($row['average'], 2).'/20' : 'Aucune note' }}
                                </td>
                                @if ($termId)
                                    <td class="p-2">
                                        <x-filament::button size="xs" color="gray" wire:click="downloadBulletin({{ $row['student']->id }})">
                                            Bulletin (PDF)
                                        </x-filament::button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                    @if ($average = $this->getClassAverage())
                        <tfoot>
                            <tr class="border-t border-gray-200 font-medium dark:border-white/10">
                                <td class="p-2" colspan="{{ $showRanking ? 2 : 1 }}">Moyenne de classe</td>
                                <td class="p-2">{{ $average }}/20</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
