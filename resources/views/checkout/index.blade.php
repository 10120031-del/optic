<x-layout title="Checkout">
    <div class="mb-8">
        <p class="eyebrow">{{ __('Almost there') }}</p>
        <h1 class="mt-1 font-display text-3xl font-semibold text-ink">{{ __('Checkout') }}</h1>
    </div>

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
                <p class="eyebrow mb-4">{{ __('Payment method') }}</p>
                <div class="space-y-2.5">
                    @foreach (['card' => __('Card'), 'paypal' => __('PayPal'), 'cash_on_delivery' => __('Cash on delivery'), 'bank_transfer' => __('Bank transfer')] as $value => $label)
                        <label class="flex cursor-pointer items-center gap-3 rounded-[3px] border border-hairline-strong p-3 transition-colors has-[:checked]:border-accent has-[:checked]:bg-accent-soft">
                            <input type="radio" name="payment_method" value="{{ $value }}" class="accent-accent" @checked($loop->first) required>
                            <span class="text-sm text-ink">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-ink-faint">{{ __('Card and PayPal are marked paid immediately in this environment; cash on delivery and bank transfer stay pending until fulfilled.') }}</p>
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
