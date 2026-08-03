<x-filament-panels::page>
    <x-filament::section>
        <div>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Classe</label>
            <x-filament::input.wrapper class="max-w-sm">
                <x-filament::input.select wire:model.live="schoolClassId">
                    @foreach ($this->schoolClasses as $schoolClass)
                        <option value="{{ $schoolClass->id }}">{{ $schoolClass->name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
    </x-filament::section>

    <x-filament::section class="mt-6">
        @php($students = $this->students)

        @if ($students->isEmpty())
            <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                Aucun élève à besoins particuliers dans cette classe.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left">
                            <th class="p-2">Élève</th>
                            <th class="p-2">Besoins particuliers</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($students as $student)
                            <tr class="border-t border-gray-100 dark:border-white/5">
                                <td class="p-2 align-top">
                                    <x-student-name :student="$student" />
                                </td>
                                <td class="p-2 align-top">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($student->special_needs as $tag)
                                            <x-filament::badge color="warning">{{ $tag }}</x-filament::badge>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
