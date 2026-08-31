{{--
    Horizontal bar list, scaled against the largest row so the longest bar
    always fills the track. Rows: [['label' => string, 'value' => numeric,
    'meta' => ?string, 'href' => ?string], ...]
--}}
@props([
    'rows' => [],
    'empty' => null,
    'color' => 'var(--color-ink)',
])

@php
    $rows = collect($rows)->values();
    $max = max(1, $rows->max('value') ?? 0);
@endphp

@if ($rows->isEmpty())
    <p class="text-sm text-ink-faint">{{ $empty ?? __('Nothing to chart yet.') }}</p>
@else
    <div class="space-y-3">
        @foreach ($rows as $row)
            {{-- Empty rows stay empty; anything non-zero keeps a visible sliver. --}}
            @php $width = $row['value'] > 0 ? max(1.5, ($row['value'] / $max) * 100) : 0; @endphp
            <div>
                <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                    @if (! empty($row['href']))
                        <a href="{{ $row['href'] }}" class="min-w-0 truncate text-ink-soft hover:text-ink">{{ $row['label'] }}</a>
                    @else
                        <span class="min-w-0 truncate text-ink-soft">{{ $row['label'] }}</span>
                    @endif
                    <span class="shrink-0 font-mono text-xs text-ink-faint">{{ $row['meta'] ?? $row['value'] }}</span>
                </div>
                <div class="h-1.5 w-full rounded-[2px] bg-hairline">
                    <div class="h-full rounded-[2px]" style="width: {{ round($width, 2) }}%; background: {{ $row['color'] ?? $color }}"></div>
                </div>
            </div>
        @endforeach
    </div>
@endif
