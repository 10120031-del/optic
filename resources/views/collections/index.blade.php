<x-layout title="Collections">
    <div class="mb-10">
        <p class="eyebrow">{{ __('Drops') }}</p>
        <h1 class="mt-1 font-display text-3xl font-semibold text-ink">{{ __('Collections') }}</h1>
        <p class="mt-2 max-w-xl text-sm text-ink-soft">
            {{ __('Curated drops — frames and lenses picked to sit together.') }}
        </p>
    </div>

    @if ($collections->isEmpty())
        <x-empty-state
            :title="__('No collections yet')"
            :description="__('Nothing has dropped so far. The full catalogue is always here in the meantime.')">
            <x-slot:action>
                <a href="{{ route('frames.index') }}" class="btn-accent btn-sm">{{ __('Shop all frames') }}</a>
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            @foreach ($collections as $collection)
                <a href="{{ route('collections.show', $collection) }}"
                   class="group tick-frame reveal relative overflow-hidden border border-hairline bg-accent-soft transition-all duration-200 hover:-translate-y-1 hover:border-accent hover:shadow-[6px_6px_0_var(--color-accent)]">
                    <div class="relative aspect-[3/2] overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <svg class="w-1/2 text-hairline-strong" viewBox="0 0 120 60" fill="none" stroke="currentColor" stroke-width="1.2">
                                <circle cx="34" cy="30" r="24" />
                                <circle cx="86" cy="30" r="24" />
                                <path d="M58 30h4" stroke-linecap="round" />
                                <path d="M10 30c0-6 4-10 8-10" stroke-linecap="round" />
                                <path d="M110 30c0-6-4-10-8-10" stroke-linecap="round" />
                            </svg>
                        </div>
                        @if ($collection->cover_image)
                            <img src="{{ Storage::disk('public')->url($collection->cover_image) }}"
                                 alt="{{ $collection->name }}"
                                 class="absolute inset-0 h-full w-full object-cover transition-transform duration-700 group-hover:scale-105"
                                 loading="lazy"
                                 onerror="this.remove()">
                        @endif
                    </div>

                    <div class="border-t border-hairline bg-white p-6">
                        <p class="eyebrow">
                            {{ $collection->announced_at->format('M Y') }}
                            &middot;
                            {{ trans_choice(':count piece|:count pieces', $collection->frames_count + $collection->contact_lenses_count) }}
                        </p>
                        <h2 class="mt-1 font-display text-xl font-semibold text-ink transition-colors group-hover:text-accent">
                            {{ $collection->name }}
                        </h2>
                        @if ($collection->description)
                            <p class="mt-1 text-sm text-ink-soft">{{ Str::limit($collection->description, 110) }}</p>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-layout>
