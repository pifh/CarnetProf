@php($imgClass = $class ?? 'h-full w-full object-cover')

@if ($student->photo_url)
    <img src="{{ $student->photo_url }}" alt="{{ $student->full_name }}" class="{{ $imgClass }}" />
@else
    @php($initials = mb_strtoupper(mb_substr($student->first_name, 0, 1).mb_substr($student->last_name, 0, 1)))
    <div class="{{ $imgClass }} flex items-center justify-center bg-gray-200 font-semibold text-gray-500 dark:bg-white/10 dark:text-gray-400">
        {{ $initials }}
    </div>
@endif
