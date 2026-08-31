<x-layout :title="$collection->name">
    <nav class="mb-8 font-mono text-[11px] uppercase tracking-[0.06em] text-ink-faint">
        <a href="{{ route('collections.index') }}" class="hover:text-ink">{{ __('Collections') }}</a>
        <span class="mx-1.5">/</span>
        <span class="text-ink-soft">{{ $collection->name }}</span>
    </nav>

    {{-- Banner: the cover if the owner uploaded one, otherwise type alone. --}}
    <section class="tick-frame-accent relative overflow-hidden border border-hairline bg-accent-soft">
        @if ($collection->cover_image)
            <div class="relative aspect-[3/1] w-full">
                <img src="{{ Storage::disk('public')->url($collection->cover_image) }}"
                     alt="{{ $collection->name }}"
                     class="absolute inset-0 h-full w-full object-cover"
                     onerror="this.remove()">
                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-ink/85 via-ink/40 to-transparent p-8 pt-24">
                    <p class="font-mono text-[11px] uppercase tracking-[0.12em] text-white/70">{{ __('New collection') }}</p>
                    <h1 class="mt-1 font-display text-3xl font-semibold tracking-tight text-white sm:text-4xl">{{ $collection->name }}</h1>
                </div>
            </div>
        @else
            <div class="p-8 sm:p-14">
                <p class="badge-pop bg-white">{{ __('New collection') }}</p>
                <h1 class="mt-4 font-display text-3xl font-semibold tracking-tight text-ink sm:text-5xl">{{ $collection->name }}</h1>
            </div>
        @endif
    </section>

    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            @if ($collection->description)
                <p class="max-w-xl text-base leading-relaxed text-ink-soft">{{ $collection->description }}</p>
            @endif
        </div>
        <p class="font-mono text-xs text-ink-faint">
            {{ trans_choice(':count piece|:count pieces', $collection->frames->count() + $collection->contactLenses->count()) }}
            &middot;
            {{ __('Dropped :date', ['date' => $collection->announced_at->format('M j, Y')]) }}
        </p>
    </div>

    @if ($collection->frames->isNotEmpty())
        <section class="mt-14">
            <div class="mb-8 flex items-end justify-between gap-4">
                <div>
                    <p class="eyebrow">{{ __('In this collection') }}</p>
                    <h2 class="mt-1 font-display text-2xl font-semibold text-ink">{{ __('Frames') }}</h2>
                </div>
                <a href="{{ route('frames.index') }}" class="text-sm font-medium text-accent hover:underline">{{ __('All eyeglasses →') }}</a>
            </div>

            <div class="grid grid-cols-2 gap-x-6 gap-y-10 sm:grid-cols-4">
                @foreach ($collection->frames as $frame)
                    <x-frame-card :frame="$frame" />
                @endforeach
            </div>
        </section>
    @endif

    @if ($collection->contactLenses->isNotEmpty())
        <section class="mt-20">
            <div class="mb-8 flex items-end justify-between gap-4">
                <div>
                    <p class="eyebrow">{{ __('In this collection') }}</p>
                    <h2 class="mt-1 font-display text-2xl font-semibold text-ink">{{ __('Contact lenses') }}</h2>
                </div>
                <a href="{{ route('contact-lenses.index') }}" class="text-sm font-medium text-accent hover:underline">{{ __('All contact lenses →') }}</a>
            </div>

            <div class="grid grid-cols-2 gap-x-6 gap-y-10 sm:grid-cols-3">
                @foreach ($collection->contactLenses as $lens)
                    <x-contact-lens-card :lens="$lens" />
                @endforeach
            </div>
        </section>
    @endif

    {{--
        Everything in the collection could have been deactivated since it was
        announced — the eager loads filter on is_active — so the page must
        still say something rather than trailing off into whitespace.
    --}}
    @if ($collection->frames->isEmpty() && $collection->contactLenses->isEmpty())
        <div class="mt-14">
            <x-empty-state
                :title="__('Nothing in this collection is available right now')"
                :description="__('These pieces have sold through or been taken down. The rest of the catalogue is still here.')">
                <x-slot:action>
                    <a href="{{ route('frames.index') }}" class="btn-accent btn-sm">{{ __('Shop all frames') }}</a>
                </x-slot:action>
            </x-empty-state>
        </div>
    @endif
</x-layout>
