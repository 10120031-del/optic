@props(['lens'])

<a href="{{ route('contact-lenses.show', $lens) }}" class="card-product group reveal block">
    <div class="tick-frame relative aspect-[4/3] overflow-hidden bg-white">
        @if ($lens->image_path)
            <img src="{{ Storage::disk('public')->url($lens->image_path) }}" alt="{{ $lens->name }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.06]" loading="lazy">
        @else
            <div class="flex h-full w-full items-center justify-center">
                <svg class="size-10 text-hairline-strong" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3">
                    <circle cx="12" cy="12" r="9" />
                    <circle cx="12" cy="12" r="3.2" />
                </svg>
            </div>
        @endif
    </div>

    <div class="mt-3 space-y-1 px-3 pb-3">
        <p class="eyebrow">{{ $lens->brand }} &middot; {{ str($lens->type)->headline() }}</p>
        <h3 class="text-sm font-medium text-ink transition-colors group-hover:text-accent">{{ $lens->name }}</h3>
        <x-rating :value="$lens->reviews_avg_rating" :count="$lens->reviews_count" />
        <div class="mt-2 flex items-center justify-between gap-2">
            <p class="font-mono text-sm font-medium text-accent">${{ number_format($lens->price, 2) }} <span class="text-ink-faint">/ {{ $lens->pack_size }}pk</span></p>
            <span class="pill-cta">
                {{ __('View') }}
                <svg class="size-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </span>
        </div>
    </div>
</a>
