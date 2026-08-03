@if (empty($points))
    <p class="text-sm text-gray-500 dark:text-gray-400">Aucune note chiffrée pour cette période — impossible de tracer une courbe.</p>
@else
    <svg viewBox="0 0 320 110" class="h-40 w-full text-primary-600" preserveAspectRatio="none">
        <line x1="30" y1="10" x2="310" y2="10" stroke="currentColor" stroke-width="1" class="text-gray-200 dark:text-white/10" />
        <line x1="30" y1="55" x2="310" y2="55" stroke="currentColor" stroke-width="1" class="text-gray-200 dark:text-white/10" />
        <line x1="30" y1="100" x2="310" y2="100" stroke="currentColor" stroke-width="1" class="text-gray-300 dark:text-white/20" />

        <text x="25" y="13" text-anchor="end" font-size="8" class="fill-gray-400 dark:fill-gray-500">20</text>
        <text x="25" y="58" text-anchor="end" font-size="8" class="fill-gray-400 dark:fill-gray-500">10</text>
        <text x="25" y="103" text-anchor="end" font-size="8" class="fill-gray-400 dark:fill-gray-500">0</text>

        <polyline
            points="{{ collect($points)->map(fn ($point) => (30 + ($point['x'] / 100) * 280).','.(10 + ($point['y'] / 100) * 90))->implode(' ') }}"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linejoin="round"
            stroke-linecap="round"
        />

        @foreach ($points as $point)
            <circle
                cx="{{ 30 + ($point['x'] / 100) * 280 }}"
                cy="{{ 10 + ($point['y'] / 100) * 90 }}"
                r="2.5"
                fill="currentColor"
            >
                <title>{{ $point['label'] }}{{ $point['date'] ? ' — '.$point['date'] : '' }} : {{ rtrim(rtrim(number_format($point['score'], 2), '0'), '.') }}/20</title>
            </circle>
        @endforeach
    </svg>
@endif
