<x-layout title="Eyeglasses">
    <div @class([
        'theme-men' => ($filters['gender'] ?? '') === 'men',
        'theme-women' => ($filters['gender'] ?? '') === 'women',
    ])>
    <div class="mb-8 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="eyebrow">{{ __('Catalog') }}</p>
            <h1 class="mt-1 font-display text-3xl font-semibold text-ink">{{ __('Eyeglasses') }}</h1>
        </div>
        <p class="font-mono text-xs text-ink-faint">{{ $frames->total() }} {{ Str::plural('frame', $frames->total()) }}</p>
    </div>

    <div class="grid grid-cols-1 gap-10 lg:grid-cols-[240px_1fr]">
        <aside>
            <form method="GET" action="{{ route('frames.index') }}" class="space-y-6">
                <div>
                    <label class="field-label" for="q">{{ __('Search') }}</label>
                    <input type="text" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Name or brand') }}" class="input">
                </div>

                <div>
                    <label class="field-label" for="gender">{{ __('Gender') }}</label>
                    <select id="gender" name="gender" class="select">
                        <option value="">{{ __('Any') }}</option>
                        @foreach ($genders as $gender)
                            <option value="{{ $gender }}" @selected(($filters['gender'] ?? '') === $gender)>{{ str($gender)->headline() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="field-label" for="shape">{{ __('Shape') }}</label>
                    <select id="shape" name="shape" class="select">
                        <option value="">{{ __('Any') }}</option>
                        @foreach ($shapes as $shape)
                            <option value="{{ $shape }}" @selected(($filters['shape'] ?? '') === $shape)>{{ str($shape)->headline() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="field-label" for="size">{{ __('Size') }}</label>
                    <select id="size" name="size" class="select">
                        <option value="">{{ __('Any') }}</option>
                        @foreach ($sizes as $size)
                            <option value="{{ $size }}" @selected(($filters['size'] ?? '') === $size)>{{ str($size)->headline() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="field-label" for="category">{{ __('Category') }}</label>
                    <select id="category" name="category" class="select">
                        <option value="">{{ __('Any') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ str($category)->headline() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="field-label" for="color">{{ __('Color') }}</label>
                    <input type="text" id="color" name="color" value="{{ $filters['color'] ?? '' }}" placeholder="{{ __('e.g. tortoise') }}" class="input">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="field-label" for="min_price">{{ __('Min $') }}</label>
                        <input type="number" step="0.01" min="0" id="min_price" name="min_price" value="{{ $filters['min_price'] ?? '' }}" class="input">
                    </div>
                    <div>
                        <label class="field-label" for="max_price">{{ __('Max $') }}</label>
                        <input type="number" step="0.01" min="0" id="max_price" name="max_price" value="{{ $filters['max_price'] ?? '' }}" class="input">
                    </div>
                </div>

                <input type="hidden" name="sort" value="{{ $filters['sort'] ?? '' }}">

                <div class="flex gap-2">
                    <button type="submit" class="btn-accent btn-sm w-full">{{ __('Apply') }}</button>
                    <a href="{{ route('frames.index') }}" class="btn-ghost btn-sm">{{ __('Reset') }}</a>
                </div>
            </form>
        </aside>

        <div>
            <div class="hairline-bottom mb-6 flex items-center justify-between pb-4">
                <p class="text-sm text-ink-soft">{{ __('Showing') }} {{ $frames->firstItem() ?? 0 }}&ndash;{{ $frames->lastItem() ?? 0 }}</p>
                <form method="GET" action="{{ route('frames.index') }}" class="flex items-center gap-2">
                    @foreach ($filters as $key => $value)
                        @if ($key !== 'sort' && $value !== null && $value !== '')
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <label class="field-label !mb-0" for="sort">{{ __('Sort') }}</label>
                    <select id="sort" name="sort" class="select !py-1.5 !text-xs" onchange="this.form.submit()">
                        <option value="" @selected(($filters['sort'] ?? '') === '')>{{ __('Featured') }}</option>
                        <option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>{{ __('Newest') }}</option>
                        <option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>{{ __('Price: Low to High') }}</option>
                        <option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>{{ __('Price: High to Low') }}</option>
                    </select>
                </form>
            </div>

            @if ($frames->isEmpty())
                <x-empty-state :title="__('No frames match those filters')" :description="__('Try widening your search or clearing a filter.')">
                    <x-slot:action>
                        <a href="{{ route('frames.index') }}" class="btn-outline btn-sm">{{ __('Clear filters') }}</a>
                    </x-slot:action>
                </x-empty-state>
            @else
                <div class="grid grid-cols-2 gap-x-6 gap-y-10 sm:grid-cols-3">
                    @foreach ($frames as $frame)
                        <x-frame-card :frame="$frame" />
                    @endforeach
                </div>

                {{ $frames->links() }}
            @endif
        </div>
    </div>
    </div>
</x-layout>
