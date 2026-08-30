<x-layout title="Contact Lenses">
    <div class="mb-8 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="eyebrow">{{ __('Catalog') }}</p>
            <h1 class="mt-1 font-display text-3xl font-semibold text-ink">{{ __('Contact Lenses') }}</h1>
        </div>
        <p class="font-mono text-xs text-ink-faint">{{ $lenses->total() }} {{ Str::plural('product', $lenses->total()) }}</p>
    </div>

    <div class="grid grid-cols-1 gap-10 lg:grid-cols-[240px_1fr]">
        <aside>
            <form method="GET" action="{{ route('contact-lenses.index') }}" class="space-y-6">
                <div>
                    <label class="field-label" for="q">{{ __('Search') }}</label>
                    <input type="text" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Name or brand') }}" class="input">
                </div>

                <div>
                    <label class="field-label" for="type">{{ __('Wear schedule') }}</label>
                    <select id="type" name="type" class="select">
                        <option value="">{{ __('Any') }}</option>
                        @foreach ($types as $type)
                            <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ str($type)->headline() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="field-label" for="material">{{ __('Material') }}</label>
                    <select id="material" name="material" class="select">
                        <option value="">{{ __('Any') }}</option>
                        <option value="hydrogel" @selected(($filters['material'] ?? '') === 'hydrogel')>{{ __('Hydrogel') }}</option>
                        <option value="silicone_hydrogel" @selected(($filters['material'] ?? '') === 'silicone_hydrogel')>{{ __('Silicone Hydrogel') }}</option>
                    </select>
                </div>

                <div>
                    <label class="field-label" for="color">{{ __('Color') }}</label>
                    <input type="text" id="color" name="color" value="{{ $filters['color'] ?? '' }}" class="input">
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="btn-accent btn-sm w-full">{{ __('Apply') }}</button>
                    <a href="{{ route('contact-lenses.index') }}" class="btn-ghost btn-sm">{{ __('Reset') }}</a>
                </div>
            </form>
        </aside>

        <div>
            @if ($lenses->isEmpty())
                <x-empty-state :title="__('No contact lenses match those filters')" :description="__('Try widening your search or clearing a filter.')">
                    <x-slot:action>
                        <a href="{{ route('contact-lenses.index') }}" class="btn-outline btn-sm">{{ __('Clear filters') }}</a>
                    </x-slot:action>
                </x-empty-state>
            @else
                <div class="grid grid-cols-2 gap-x-6 gap-y-10 sm:grid-cols-3">
                    @foreach ($lenses as $lens)
                        <x-contact-lens-card :lens="$lens" />
                    @endforeach
                </div>

                {{ $lenses->links() }}
            @endif
        </div>
    </div>
</x-layout>
