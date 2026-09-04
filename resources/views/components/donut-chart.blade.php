{{--
    Dependency-free donut. The circle's radius is chosen so its circumference
    is exactly 100 units, which lets every segment be dashed straight in
    percentages; rotating -90° moves the start of the arc to twelve o'clock.

    Segments: [['label' => string, 'value' => numeric, 'meta' => ?string], ...]
    Colours cycle through the theme's own tokens, so a re-themed accent
    (see the [data-theme] blocks in app.css) carries into the charts.
--}}
@props([
    'segments' => [],
    'centerValue' => null,
    'centerLabel' => null,
    'empty' => null,
])

@php
    $palette = [
        'var(--color-signal)',
        'var(--color-accent)',
        'var(--color-pop)',
        'var(--color-warn)',
        'var(--color-ink)',
        'var(--color-ink-soft)',
        'var(--color-hairline-strong)',
    ];

    $slices = collect($segments)
        ->filter(fn ($segment) => ($segment['value'] ?? 0) > 0)
        ->values();

    $total = $slices->sum('value');
    $offset = 0;
@endphp

@if ($slices->isEmpty())
    <p class="text-sm text-ink-faint">{{ $empty ?? __('Nothing to chart yet.') }}</p>
@else
    <div class="flex flex-wrap items-center gap-x-6 gap-y-4">
        <div class="relative shrink-0">
            <svg viewBox="0 0 42 42" class="size-32" role="img" aria-label="{{ $centerLabel ?? __('Distribution') }}">
                <circle cx="21" cy="21" r="15.91549431" fill="none" stroke-width="5" style="stroke: var(--color-hairline)" />
                @foreach ($slices as $index => $slice)
                    @php
                        $percent = ($slice['value'] / $total) * 100;
                        $color = $palette[$index % count($palette)];
                        $dashOffset = fmod(100 - $offset, 100);
                        $offset += $percent;
                    @endphp
                    <circle cx="21" cy="21" r="15.91549431" fill="none" stroke-width="5"
                            stroke-dasharray="{{ round($percent, 3) }} {{ round(100 - $percent, 3) }}"
                            stroke-dashoffset="{{ round($dashOffset, 3) }}"
                            transform="rotate(-90 21 21)"
                            style="stroke: {{ $color }}">
                        <title>{{ $slice['label'] }} — {{ round($percent) }}%</title>
                    </circle>
                @endforeach
            </svg>
            @if ($centerValue !== null)
                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                    <span class="font-display text-lg font-semibold leading-none text-ink">{{ $centerValue }}</span>
                    @if ($centerLabel)
                        <span class="mt-1 font-mono text-[9px] uppercase tracking-[0.06em] text-ink-faint">{{ $centerLabel }}</span>
                    @endif
                </div>
            @endif
        </div>

        <ul class="w-full min-w-0 space-y-2 sm:w-auto sm:flex-1">
            @foreach ($slices as $index => $slice)
                <li class="flex items-center gap-2.5 text-sm">
                    <span class="size-2.5 shrink-0 rounded-[2px]" style="background: {{ $palette[$index % count($palette)] }}"></span>
                    <span class="min-w-0 flex-1 truncate text-ink-soft">{{ $slice['label'] }}</span>
                    <span class="shrink-0 font-mono text-xs text-ink-faint">
                        {{ $slice['meta'] ?? $slice['value'] }} &middot; {{ round(($slice['value'] / $total) * 100) }}%
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
