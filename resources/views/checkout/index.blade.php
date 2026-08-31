<x-layout title="Checkout">
    <div class="mb-8">
        <p class="eyebrow">{{ __('Almost there') }}</p>
        <h1 class="mt-1 font-display text-3xl font-semibold text-ink">{{ __('Checkout') }}</h1>
    </div>

    @if ($shortages)
        <div class="panel border-warn/30 bg-warn-soft mb-8 flex items-start gap-3 px-4 py-3" role="alert">
            <svg class="mt-0.5 size-4 shrink-0 text-warn" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6">
                <circle cx="10" cy="10" r="7.5" />
                <path d="M10 6v4.5" stroke-linecap="round" />
                <circle cx="10" cy="13.4" r="0.6" fill="currentColor" stroke="none" />
            </svg>
            <div class="text-sm text-ink">
                <p class="font-medium">{{ __('We cannot fill all of this right now:') }}</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-4 text-ink-soft">
                    @foreach ($shortages as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('cart.index') }}" class="mt-2 inline-block text-xs text-ink underline hover:no-underline">{{ __('Adjust your cart') }}</a>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-12 lg:grid-cols-[1fr_360px]">
        <form method="POST" action="{{ route('checkout.store') }}" class="space-y-8">
            @csrf

            <section>
                <p class="eyebrow mb-4">{{ __('Shipping address') }}</p>
                <div class="space-y-4">
                    <div>
                        <label class="field-label" for="shipping_address_line">{{ __('Street address') }}</label>
                        <input type="text" id="shipping_address_line" name="shipping_address_line" value="{{ old('shipping_address_line') }}" required class="input">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="field-label" for="shipping_city">{{ __('City') }}</label>
                            <input type="text" id="shipping_city" name="shipping_city" value="{{ old('shipping_city') }}" required class="input">
                        </div>
                        <div>
                            <label class="field-label" for="shipping_postal_code">{{ __('Postal code') }}</label>
                            <input type="text" id="shipping_postal_code" name="shipping_postal_code" value="{{ old('shipping_postal_code') }}" class="input">
                        </div>
                    </div>
                    <div>
                        <label class="field-label" for="shipping_country">{{ __('Country') }}</label>
                        <input type="text" id="shipping_country" name="shipping_country" value="{{ old('shipping_country') }}" required class="input">
                    </div>
                </div>
            </section>

            <section class="hairline-top pt-8">
                <p class="eyebrow mb-4">{{ __('Payment') }}</p>
                <div class="flex items-start gap-3 rounded-[3px] border border-hairline-strong bg-accent-soft p-4">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" class="mt-0.5 size-5 shrink-0 text-accent" aria-hidden="true">
                        <rect x="2.5" y="6" width="19" height="12" rx="2" />
                        <circle cx="12" cy="12" r="2.4" />
                        <path d="M6 12h.01M18 12h.01" />
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-ink">{{ __('Cash on delivery') }}</p>
                        <p class="mt-1 text-xs text-ink-soft">
                            {{ __('Pay the courier in cash when your order arrives — :total, including shipping. Nothing is charged now, and we never ask for card details.', ['total' => '$'.number_format($totals['total'], 2)]) }}
                        </p>
                    </div>
                </div>
            </section>

            <button type="submit" class="btn-accent w-full">{{ __('Place order') }}</button>
        </form>

        <div class="panel h-fit p-6">
            <p class="eyebrow mb-4">{{ __('Order summary') }}</p>
            <div class="space-y-4">
                @foreach ($cart->eyeglasses as $line)
                    @php
                        $featuresTotal = $line->features->sum(fn ($f) => (float) $f->price);
                        $lineTotal = ((float) $line->frame->price + (float) $line->lens->price + $featuresTotal) * $line->quantity;
                    @endphp
                    <div class="flex items-start justify-between gap-3 text-sm">
                        <div>
                            <p class="text-ink">{{ $line->frame->name }} <span class="text-ink-faint">&times;{{ $line->quantity }}</span></p>
                            <p class="text-xs text-ink-faint">{{ $line->lens->name }}</p>
                        </div>
                        <span class="whitespace-nowrap font-mono text-ink">${{ number_format($lineTotal, 2) }}</span>
                    </div>
                @endforeach
                @foreach ($cart->contactLenses as $line)
                    <div class="flex items-start justify-between gap-3 text-sm">
                        <p class="text-ink">{{ $line->contactLens->name }} <span class="text-ink-faint">&times;{{ $line->quantity }}</span></p>
                        <span class="whitespace-nowrap font-mono text-ink">${{ number_format($line->contactLens->price * $line->quantity, 2) }}</span>
                    </div>
                @endforeach
            </div>

            <div class="hairline-top mt-5 space-y-2 pt-5 text-sm">
                <div class="flex justify-between"><span class="text-ink-soft">{{ __('Subtotal') }}</span><span class="font-mono text-ink">${{ number_format($totals['subtotal'], 2) }}</span></div>
                <div class="flex justify-between"><span class="text-ink-soft">{{ __('Shipping') }}</span><span class="font-mono text-ink">${{ number_format($totals['shipping_cost'], 2) }}</span></div>
                <div class="flex justify-between"><span class="text-ink-soft">{{ __('Tax') }}</span><span class="font-mono text-ink">${{ number_format($totals['tax'], 2) }}</span></div>
                <div class="hairline-top flex justify-between pt-2 text-base font-medium"><span class="text-ink">{{ __('Total') }}</span><span class="font-mono text-accent">${{ number_format($totals['total'], 2) }}</span></div>
            </div>
        </div>
    </div>
</x-layout>
