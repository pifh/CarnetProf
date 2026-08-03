@props(['student'])

<span
    {{ $attributes->merge(['class' => 'cursor-pointer hover:underline']) }}
    wire:click.stop="$dispatch('open-student-preview', { studentId: {{ $student->id }} })"
>{{ $student->first_name }} {{ $student->last_name }}</span>
