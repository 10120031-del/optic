@props(['value' => null, 'count' => 0, 'size' => 'sm'])

@php
    $rounded = $value ? round($value * 2) / 2 : 0;
    $dims = $size === 'lg' ? 'size-4' : 'size-3.5';
@endphp

<div class="inline-flex items-center gap-1.5" title="{{ $value ? number_format($value, 1).' / 5' : 'No ratings yet' }}">
    <div class="flex items-center gap-0.5">
        @for ($i = 1; $i <= 5; $i++)
            <svg class="{{ $dims }} {{ $rounded >= $i ? 'text-ink' : 'text-hairline-strong' }}" viewBox="0 0 20 20" fill="{{ $rounded >= $i ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.2">
                <path d="M10 2.5l2.29 4.64 5.12.74-3.7 3.61.87 5.1L10 14.03l-4.58 2.56.87-5.1-3.7-3.61 5.12-.74z" stroke-linejoin="round" />
            </svg>
        @endfor
    </div>
    @if ($value)
        <span class="font-mono text-[11px] text-ink-faint">{{ number_format($value, 1) }}@if($count) &middot; {{ $count }} @endif</span>
    @else
        <span class="font-mono text-[11px] text-ink-faint">{{ __('No reviews yet') }}</span>
    @endif
</div>
