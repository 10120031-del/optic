@props(['frame'])

<a href="{{ route('frames.show', $frame) }}" class="card-product group reveal block">
    <div class="tick-frame relative aspect-[4/3] overflow-hidden bg-white">
        @if ($frame->primaryImage)
            <img src="{{ Storage::disk('public')->url($frame->primaryImage->path) }}" alt="{{ $frame->primaryImage->alt_text ?? $frame->name }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.06]" loading="lazy">
        @else
            <div class="flex h-full w-full items-center justify-center">
                <svg class="size-10 text-hairline-strong" viewBox="0 0 48 24" fill="none" stroke="currentColor" stroke-width="1.3">
                    <circle cx="12" cy="12" r="9.5" />
                    <circle cx="36" cy="12" r="9.5" />
                    <path d="M21.5 12h5" />
                </svg>
            </div>
        @endif
        @if ($frame->stock <= 5 && $frame->stock > 0)
            <span class="badge-warn absolute left-2 top-2 bg-white">{{ __('Low stock') }}</span>
        @elseif ($frame->stock === 0)
            <span class="badge-danger absolute left-2 top-2 bg-white">{{ __('Out of stock') }}</span>
        @endif
    </div>

    <div class="mt-3 space-y-1 px-3 pb-3">
        <p class="eyebrow">{{ $frame->brand }}</p>
        <h3 class="text-sm font-medium text-ink transition-colors group-hover:text-accent">{{ $frame->name }}</h3>
        <x-rating :value="$frame->reviews_avg_rating" :count="$frame->reviews_count" />
        <div class="mt-2 flex items-center justify-between gap-2">
            <p class="font-mono text-sm font-medium text-accent">${{ number_format($frame->price, 2) }}</p>
            <span class="pill-cta">
                {{ __('View') }}
                <svg class="size-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </span>
        </div>
    </div>
</a>
