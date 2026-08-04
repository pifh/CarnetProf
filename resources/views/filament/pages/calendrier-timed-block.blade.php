@php($classes = \App\Support\CalendarCategories::chipClasses($item->type))
<div
    class="absolute overflow-hidden rounded px-1.5 py-0.5 text-[11px] leading-tight {{ $classes }}"
    style="top: {{ $top }}px; height: {{ $height }}px; left: calc({{ $left }}% + 2px); width: calc({{ $width }}% - 4px);"
>
    @if ($item->url)
        <a href="{{ $item->url }}" title="{{ $item->title }}" class="block h-full truncate hover:opacity-80">
            <span class="font-medium">{{ $item->startsAt->format('H:i') }}</span> {{ $item->title }}
        </a>
    @elseif ($item->studentId)
        <button type="button" wire:click="$dispatch('open-student-preview', { studentId: {{ $item->studentId }} })" title="{{ $item->title }}" class="block h-full w-full truncate text-left hover:opacity-80">
            <span class="font-medium">{{ $item->startsAt->format('H:i') }}</span> {{ $item->title }}
        </button>
    @else
        <span title="{{ $item->title }}" class="block h-full truncate">
            <span class="font-medium">{{ $item->startsAt->format('H:i') }}</span> {{ $item->title }}
        </span>
    @endif
</div>
