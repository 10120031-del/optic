@props(['title', 'description' => null])

<div class="tick-frame flex flex-col items-center gap-3 border border-dashed border-hairline-strong px-8 py-16 text-center">
    <svg class="size-8 text-ink-faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3">
        <circle cx="10.5" cy="10.5" r="6.5" />
        <path d="M15.2 15.2L20 20" stroke-linecap="round" />
    </svg>
    <div>
        <p class="font-display text-base text-ink">{{ $title }}</p>
        @if ($description)
            <p class="mt-1 max-w-sm text-sm text-ink-soft">{{ $description }}</p>
        @endif
    </div>
    @isset($action)
        <div class="mt-2">{{ $action }}</div>
    @endisset
</div>
