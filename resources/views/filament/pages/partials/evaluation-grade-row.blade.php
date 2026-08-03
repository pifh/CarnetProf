<tr class="border-t border-gray-100 dark:border-white/5" wire:key="student-{{ $student->id }}">
    <td class="p-2">{{ $student->first_name }} {{ $student->last_name }}</td>
    <td class="p-2">
        <div class="flex items-center gap-1">
            <x-filament::input.wrapper class="max-w-[6rem]">
                <x-filament::input
                    type="text"
                    inputmode="decimal"
                    :value="$student->grade?->score"
                    wire:change="updateScore({{ $student->id }}, $event.target.value)"
                />
            </x-filament::input.wrapper>
            <span class="text-gray-500 dark:text-gray-400">/ {{ rtrim(rtrim(number_format($evaluation->max_score, 2), '0'), '.') }}</span>
        </div>
    </td>
    <td class="p-2">
        <div class="flex flex-wrap gap-1">
            @foreach ([
                'absent' => 'Absent',
                'exempted' => 'Dispensé',
                'to_retake' => 'À rattraper',
                'not_graded' => 'Non noté',
            ] as $statusValue => $statusLabel)
                <x-filament::button
                    size="xs"
                    :color="$student->grade?->status === $statusValue ? 'danger' : 'gray'"
                    wire:click="setStatus({{ $student->id }}, '{{ $statusValue }}')"
                >
                    {{ $statusLabel }}
                </x-filament::button>
            @endforeach
        </div>
    </td>
</tr>
