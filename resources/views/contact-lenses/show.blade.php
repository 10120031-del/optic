<x-layout :title="$contactLens->name">
    <nav class="mb-8 font-mono text-[11px] uppercase tracking-[0.06em] text-ink-faint">
        <a href="{{ route('contact-lenses.index') }}" class="hover:text-ink">{{ __('Contact Lenses') }}</a>
        <span class="mx-1.5">/</span>
        <span class="text-ink-soft">{{ $contactLens->name }}</span>
    </nav>

    <div class="grid grid-cols-1 gap-12 lg:grid-cols-2">
        <div class="tick-frame aspect-square overflow-hidden bg-wash">
            @if ($contactLens->image_path)
                <img src="{{ Storage::disk('public')->url($contactLens->image_path) }}" alt="{{ $contactLens->name }}" class="h-full w-full object-cover">
            @else
                <div class="flex h-full w-full items-center justify-center">
                    <svg class="size-16 text-hairline-strong" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                        <circle cx="12" cy="12" r="9" /><circle cx="12" cy="12" r="3.2" />
                    </svg>
                </div>
            @endif
        </div>

        <div>
            <p class="eyebrow">{{ $contactLens->brand }}</p>
            <h1 class="mt-1 font-display text-3xl font-semibold text-ink">{{ $contactLens->name }}</h1>
            <div class="mt-2"><x-rating :value="$contactLens->reviews_avg_rating ?? null" :count="$contactLens->approvedReviews->count()" size="lg" /></div>
            <p class="mt-4 font-mono text-2xl font-medium text-accent">${{ number_format($contactLens->price, 2) }} <span class="text-sm text-ink-faint">/ {{ $contactLens->pack_size }}pk</span></p>

            @if ($contactLens->description)
                <p class="mt-4 text-sm leading-relaxed text-ink-soft">{{ $contactLens->description }}</p>
            @endif

            <dl class="hairline-top mt-6 grid grid-cols-2 gap-x-4 gap-y-2 pt-6 text-xs sm:grid-cols-3">
                <div><dt class="text-ink-faint">{{ __('Type') }}</dt><dd class="text-ink-soft">{{ str($contactLens->type)->headline() }}</dd></div>
                <div><dt class="text-ink-faint">{{ __('Material') }}</dt><dd class="text-ink-soft">{{ str($contactLens->material)->headline() }}</dd></div>
                @if ($contactLens->diameter)
                    <div><dt class="text-ink-faint">{{ __('Diameter') }}</dt><dd class="text-ink-soft">{{ $contactLens->diameter }}mm</dd></div>
                @endif
                @if ($contactLens->base_curve)
                    <div><dt class="text-ink-faint">{{ __('Base curve') }}</dt><dd class="text-ink-soft">{{ $contactLens->base_curve }}mm</dd></div>
                @endif
                @if ($contactLens->expiry_months)
                    <div><dt class="text-ink-faint">{{ __('Wear expiry') }}</dt><dd class="text-ink-soft">{{ $contactLens->expiry_months }} {{ __('months') }}</dd></div>
                @endif
            </dl>

            <form method="POST" action="{{ route('cart.contact-lenses.store') }}" class="panel mt-8 space-y-4 p-6">
                @csrf
                <input type="hidden" name="contact_lens_id" value="{{ $contactLens->id }}">

                <p class="eyebrow">{{ __('Power (optional — you can also add this from your account later)') }}</p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="field-label">{{ __('Left eye (OS)') }}</p>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" step="0.01" name="left_power" placeholder="{{ __('Power') }}" class="input !py-2 !text-xs">
                            <input type="number" step="0.01" name="left_cylinder" placeholder="{{ __('Cylinder') }}" class="input !py-2 !text-xs">
                            <input type="number" step="1" min="0" max="180" name="left_axis" placeholder="{{ __('Axis') }}" class="input !py-2 !text-xs col-span-2">
                        </div>
                    </div>
                    <div>
                        <p class="field-label">{{ __('Right eye (OD)') }}</p>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" step="0.01" name="right_power" placeholder="{{ __('Power') }}" class="input !py-2 !text-xs">
                            <input type="number" step="0.01" name="right_cylinder" placeholder="{{ __('Cylinder') }}" class="input !py-2 !text-xs">
                            <input type="number" step="1" min="0" max="180" name="right_axis" placeholder="{{ __('Axis') }}" class="input !py-2 !text-xs col-span-2">
                        </div>
                    </div>
                </div>

                <div class="hairline-top flex items-center justify-between pt-4">
                    <div>
                        <label class="field-label" for="quantity">{{ __('Boxes') }}</label>
                        <input type="number" id="quantity" name="quantity" value="1" min="1" max="20" class="input !w-20">
                    </div>
                    <p class="font-mono text-xl font-medium text-accent">${{ number_format($contactLens->price, 2) }}<span class="text-sm text-ink-faint">/box</span></p>
                </div>

                @if (auth()->check() && auth()->user()->canAccessAdminConsole())
                    <p class="panel mt-6 px-4 py-3 text-xs text-ink-soft">{{ __('Staff preview — ordering is disabled for admin accounts.') }}</p>
                @else
                    <button type="submit" class="btn-accent w-full">{{ __('Add to cart') }}</button>
                @endif
            </form>
        </div>
    </div>

    <x-reviews-section :reviews="$contactLens->approvedReviews" reviewable-type="contact_lens" :reviewable-id="$contactLens->id" />

    <x-product-rail
        :products="$alsoBought"
        :eyebrow="__('Bought together')"
        :title="__('Customers who bought this also bought')"
    />

    <x-product-rail
        :products="$similarLenses"
        :eyebrow="__('Similar')"
        :title="__('You may also like')"
        :note="__('Same replacement schedule and a comparable fit — matched on base curve, diameter and cost per lens.')"
    />
</x-layout>
