<x-layout :title="$frame->name">
    <nav class="mb-8 font-mono text-[11px] uppercase tracking-[0.06em] text-ink-faint">
        <a href="{{ route('frames.index') }}" class="hover:text-ink">{{ __('Eyeglasses') }}</a>
        <span class="mx-1.5">/</span>
        <span class="text-ink-soft">{{ $frame->name }}</span>
    </nav>

    <div class="grid grid-cols-1 gap-12 lg:grid-cols-2">
        {{-- Gallery --}}
        <div>
            <div class="tick-frame aspect-square overflow-hidden bg-wash" id="gallery-main">
                @if ($frame->images->isNotEmpty())
                    <img src="{{ Storage::disk('public')->url($frame->images->first()->path) }}" alt="{{ $frame->name }}" id="gallery-main-img" class="h-full w-full object-cover">
                @else
                    <div class="flex h-full w-full items-center justify-center">
                        <svg class="size-16 text-hairline-strong" viewBox="0 0 48 24" fill="none" stroke="currentColor" stroke-width="1.2">
                            <circle cx="12" cy="12" r="9.5" /><circle cx="36" cy="12" r="9.5" /><path d="M21.5 12h5" />
                        </svg>
                    </div>
                @endif
            </div>
            @if ($frame->images->count() > 1)
                <div class="mt-3 grid grid-cols-5 gap-3">
                    @foreach ($frame->images as $image)
                        <button type="button" onclick="document.getElementById('gallery-main-img').src = this.querySelector('img').src" class="aspect-square overflow-hidden border border-hairline bg-wash transition-colors hover:border-ink">
                            <img src="{{ Storage::disk('public')->url($image->path) }}" alt="" class="h-full w-full object-cover">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Details + configurator --}}
        <div>
            <p class="eyebrow">{{ $frame->brand }}</p>
            <h1 class="mt-1 font-display text-3xl font-semibold text-ink">{{ $frame->name }}</h1>
            <div class="mt-2"><x-rating :value="$frame->reviews_avg_rating" :count="$frame->approvedReviews->count()" size="lg" /></div>
            <p class="mt-4 font-mono text-2xl font-medium text-accent">${{ number_format($frame->price, 2) }}</p>

            @if ($frame->description)
                <p class="mt-4 text-sm leading-relaxed text-ink-soft">{{ $frame->description }}</p>
            @endif

            <dl class="hairline-top mt-6 grid grid-cols-2 gap-x-4 gap-y-2 pt-6 text-xs sm:grid-cols-3">
                <div><dt class="text-ink-faint">{{ __('Material') }}</dt><dd class="text-ink-soft">{{ str($frame->material)->headline() }}</dd></div>
                <div><dt class="text-ink-faint">{{ __('Shape') }}</dt><dd class="text-ink-soft">{{ str($frame->shape ?? '—')->headline() }}</dd></div>
                <div><dt class="text-ink-faint">{{ __('Size') }}</dt><dd class="text-ink-soft">{{ str($frame->size ?? '—')->headline() }}</dd></div>
                <div><dt class="text-ink-faint">{{ __('Lens width') }}</dt><dd class="text-ink-soft">{{ $frame->lens_width }}mm</dd></div>
                <div><dt class="text-ink-faint">{{ __('Bridge') }}</dt><dd class="text-ink-soft">{{ $frame->bridge_width }}mm</dd></div>
                <div><dt class="text-ink-faint">{{ __('Temple') }}</dt><dd class="text-ink-soft">{{ $frame->temple_length }}mm</dd></div>
            </dl>

            @if ($frame->faceShapes->isNotEmpty())
                <p class="mt-6 text-xs text-ink-soft">
                    {{ __('Recommended for') }}:
                    @foreach ($frame->faceShapes as $shape)
                        <a href="{{ route('face-match.recommend', $shape) }}" class="badge-neutral ml-1 hover:border-ink">{{ $shape->name }}</a>
                    @endforeach
                </p>
            @endif

            {{-- Configurator --}}
            <form method="POST" action="{{ route('cart.eyeglasses.store') }}" class="panel mt-8 p-6" id="configurator">
                @csrf
                <input type="hidden" name="frame_id" value="{{ $frame->id }}">

                <p class="eyebrow mb-4">{{ __('Step 1 — Lens package') }}</p>
                <div class="space-y-2.5">
                    @foreach ($lenses as $i => $lens)
                        <label class="flex cursor-pointer items-start gap-3 rounded-[3px] border border-hairline-strong p-3 transition-colors has-[:checked]:border-accent has-[:checked]:bg-accent-soft">
                            <input type="radio" name="lens_id" value="{{ $lens->id }}" class="mt-1 accent-accent" data-price="{{ $lens->price }}" onchange="opticsRecalc()" @checked($i === 0) required>
                            <span class="flex-1">
                                <span class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-ink">{{ $lens->name }}</span>
                                    <span class="font-mono text-sm text-ink">${{ number_format($lens->price, 2) }}</span>
                                </span>
                                <span class="mt-0.5 block text-xs text-ink-faint">{{ str($lens->material)->headline() }} &middot; {{ str($lens->type)->headline() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <p class="eyebrow mb-3 mt-6">{{ __('Step 2 — Add features') }}</p>
                @foreach ($lenses as $lens)
                    <div class="lens-features space-y-2 {{ $loop->first ? '' : 'hidden' }}" data-lens-features="{{ $lens->id }}">
                        @forelse ($lens->features as $feature)
                            <label class="flex cursor-pointer items-start gap-3 rounded-[3px] border border-hairline p-3 transition-colors has-[:checked]:border-accent has-[:checked]:bg-accent-soft">
                                <input type="checkbox" name="feature_ids[]" value="{{ $feature->id }}" class="mt-1 checkbox accent-accent" data-price="{{ $feature->price }}" onchange="opticsRecalc()" {{ $lens->id === $lenses->first()->id ? '' : 'disabled' }}>
                                <span class="flex-1">
                                    <span class="flex items-center justify-between">
                                        <span class="text-sm text-ink">{{ $feature->name }}</span>
                                        <span class="font-mono text-xs text-ink-soft">+${{ number_format($feature->price, 2) }}</span>
                                    </span>
                                    @if ($feature->description)
                                        <span class="mt-0.5 block text-xs text-ink-faint">{{ $feature->description }}</span>
                                    @endif
                                </span>
                            </label>
                        @empty
                            <p class="text-xs text-ink-faint">{{ __('No optional features for this lens package.') }}</p>
                        @endforelse
                    </div>
                @endforeach

                <details class="hairline-top mt-6 pt-6">
                    <summary class="eyebrow cursor-pointer select-none">{{ __('Step 3 — Prescription (optional)') }}</summary>
                    <p class="mt-3 text-xs text-ink-soft">{{ __('Enter your numbers now, or skip this and add them later from your account.') }}</p>
                    <div class="mt-4 grid grid-cols-2 gap-4">
                        <div>
                            <p class="field-label">{{ __('Left eye (OS)') }}</p>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="number" step="0.01" name="left_sphere" placeholder="{{ __('Sphere') }}" class="input !py-2 !text-xs">
                                <input type="number" step="0.01" name="left_cylinder" placeholder="{{ __('Cylinder') }}" class="input !py-2 !text-xs">
                                <input type="number" step="1" min="0" max="180" name="left_axis" placeholder="{{ __('Axis') }}" class="input !py-2 !text-xs">
                                <input type="number" step="0.01" name="left_add" placeholder="{{ __('Add') }}" class="input !py-2 !text-xs">
                            </div>
                        </div>
                        <div>
                            <p class="field-label">{{ __('Right eye (OD)') }}</p>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="number" step="0.01" name="right_sphere" placeholder="{{ __('Sphere') }}" class="input !py-2 !text-xs">
                                <input type="number" step="0.01" name="right_cylinder" placeholder="{{ __('Cylinder') }}" class="input !py-2 !text-xs">
                                <input type="number" step="1" min="0" max="180" name="right_axis" placeholder="{{ __('Axis') }}" class="input !py-2 !text-xs">
                                <input type="number" step="0.01" name="right_add" placeholder="{{ __('Add') }}" class="input !py-2 !text-xs">
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <input type="number" step="0.1" name="pd" placeholder="{{ __('Pupillary distance (PD)') }}" class="input !py-2 !text-xs">
                    </div>
                </details>

                <div class="hairline-top mt-6 flex items-center justify-between pt-6">
                    <div>
                        <label class="field-label" for="quantity">{{ __('Quantity') }}</label>
                        <input type="number" id="quantity" name="quantity" value="1" min="1" max="10" class="input !w-20" onchange="opticsRecalc()">
                    </div>
                    <div class="text-right">
                        <p class="eyebrow">{{ __('Proposed price') }}</p>
                        <p class="font-mono text-2xl font-medium text-accent" id="proposed-price">${{ number_format($frame->price, 2) }}</p>
                    </div>
                </div>

                @if (auth()->check() && auth()->user()->canAccessAdminConsole())
                    <p class="panel mt-6 px-4 py-3 text-xs text-ink-soft">{{ __('Staff preview — ordering is disabled for admin accounts.') }}</p>
                @else
                    <button type="submit" class="btn-accent mt-6 w-full">{{ __('Add to cart') }}</button>
                @endif
            </form>
        </div>
    </div>

    <x-reviews-section :reviews="$frame->approvedReviews" reviewable-type="frame" :reviewable-id="$frame->id" />

    <x-product-rail
        :products="$alsoBought"
        :eyebrow="__('Bought together')"
        :title="__('Customers who bought this also bought')"
    />

    <x-product-rail
        :products="$similarFrames"
        :eyebrow="__('Similar')"
        :title="__('You may also like')"
        :note="__('The closest frames in the catalogue to this one, and what other shoppers opened alongside it.')"
    />

    <script>
        const framePrice = {{ (float) $frame->price }};

        function opticsRecalc() {
            const form = document.getElementById('configurator');
            const lensInput = form.querySelector('input[name="lens_id"]:checked');
            const lensPrice = lensInput ? parseFloat(lensInput.dataset.price) : 0;
            const qty = parseInt(form.querySelector('#quantity').value || '1', 10);

            form.querySelectorAll('.lens-features').forEach((block) => {
                const isActive = lensInput && block.dataset.lensFeatures === lensInput.value;
                block.classList.toggle('hidden', !isActive);
                block.querySelectorAll('input[type=checkbox]').forEach((cb) => {
                    cb.disabled = !isActive;
                    if (!isActive) cb.checked = false;
                });
            });

            let featuresTotal = 0;
            form.querySelectorAll('input[name="feature_ids[]"]:checked').forEach((cb) => {
                featuresTotal += parseFloat(cb.dataset.price);
            });

            const total = (framePrice + lensPrice + featuresTotal) * (isNaN(qty) ? 1 : qty);
            document.getElementById('proposed-price').textContent = '$' + total.toFixed(2);
        }

        opticsRecalc();
    </script>
</x-layout>
