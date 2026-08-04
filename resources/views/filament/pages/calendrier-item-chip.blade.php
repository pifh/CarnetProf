@php($classes = \App\Support\CalendarCategories::chipClasses($item->type))
@if ($item->url)
    <a href="{{ $item->url }}" title="{{ $item->title }}" @class(['block truncate rounded hover:opacity-80', $classes, $chipClass ?? 'px-1 py-0.5 text-[11px]'])>
        {{ $label ?? $item->title }}
    </a>
@elseif ($item->studentId)
    <button type="button" wire:click="$dispatch('open-student-preview', { studentId: {{ $item->studentId }} })" title="{{ $item->title }}" @class(['block w-full truncate rounded text-left hover:opacity-80', $classes, $chipClass ?? 'px-1 py-0.5 text-[11px]'])>
        {{ $label ?? $item->title }}
    </button>
@else
    <span title="{{ $item->title }}" @class(['block truncate rounded', $classes, $chipClass ?? 'px-1 py-0.5 text-[11px]'])>
        {{ $label ?? $item->title }}
    </span>
@endif
