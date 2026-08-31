<x-layout title="Cart">
    <div class="mb-8">
        <p class="eyebrow">{{ __('Your bag') }}</p>
        <h1 class="mt-1 font-display text-3xl font-semibold text-ink">{{ __('Cart') }}</h1>
    </div>

    @if ($cart->eyeglasses->isEmpty() && $cart->contactLenses->isEmpty())
        <x-empty-state :title="__('Your cart is empty')" :description="__('Browse the catalog and build a pair of glasses or add contact lenses.')">
            <x-slot:action>
                <a href="{{ route('frames.index') }}" class="btn-accent btn-sm">{{ __('Shop eyeglasses') }}</a>
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="grid grid-cols-1 gap-12 lg:grid-cols-[1fr_320px]">
            <div class="space-y-6">
                @foreach ($cart->eyeglasses as $line)
                    <div class="panel flex gap-4 p-5">
                        <div class="tick-frame flex size-20 shrink-0 items-center justify-center bg-wash">
                            @if ($line->frame->primaryImage)
                                <img src="{{ Storage::disk('public')->url($line->frame->primaryImage->path) }}" alt="{{ $line->frame->name }}" class="h-full w-full object-cover">
                            @else
                                <svg class="size-8 text-hairline-strong" viewBox="0 0 48 24" fill="none" stroke="currentColor" stroke-width="1.3">
                                    <circle cx="12" cy="12" r="9.5" /><circle cx="36" cy="12" r="9.5" /><path d="M21.5 12h5" />
                                </svg>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-medium text-ink">{{ $line->frame->name }}</p>
                                    <p class="text-xs text-ink-faint">{{ $line->frame->brand }} &middot; {{ $line->lens->name }}</p>
                                </div>
                                <p class="whitespace-nowrap font-mono text-sm text-ink">${{ number_format($pricing->eyeglassLineTotal($line), 2) }}</p>
                            </div>

                            @if ($line->features->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach ($line->features as $feature)
                                        <span class="badge-neutral">{{ $feature->name }}</span>
                                    @endforeach
                                </div>
                            @endif

                            @if ($line->prescription || $line->left_sphere !== null || $line->right_sphere !== null)
                                <p class="mt-2 text-xs text-ink-faint">{{ __('Prescription attached') }}</p>
                            @endif

                            <div class="mt-3 flex items-center justify-between">
                                <form method="POST" action="{{ route('cart.eyeglasses.update', $line) }}" class="flex items-center gap-2">
                                    @csrf @method('PATCH')
                                    <label class="field-label !mb-0" for="qty-eg-{{ $line->id }}">{{ __('Qty') }}</label>
                                    <input type="number" id="qty-eg-{{ $line->id }}" name="quantity" value="{{ $line->quantity }}" min="1" max="10" class="input !w-16 !py-1.5 !text-xs">
                                    <button type="submit" class="btn-ghost btn-sm">{{ __('Update') }}</button>
                                </form>
                                <form method="POST" action="{{ route('cart.eyeglasses.destroy', $line) }}" onsubmit="return confirm('{{ __('Remove this item?') }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-danger hover:underline">{{ __('Remove') }}</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach

                @foreach ($cart->contactLenses as $line)
                    <div class="panel flex gap-4 p-5">
                        <div class="tick-frame flex size-20 shrink-0 items-center justify-center bg-wash">
                            @if ($line->contactLens->image_path)
                                <img src="{{ Storage::disk('public')->url($line->contactLens->image_path) }}" alt="{{ $line->contactLens->name }}" class="h-full w-full object-cover">
                            @else
                                <svg class="size-8 text-hairline-strong" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3">
                                    <circle cx="12" cy="12" r="9" /><circle cx="12" cy="12" r="3.2" />
                                </svg>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-medium text-ink">{{ $line->contactLens->name }}</p>
                                    <p class="text-xs text-ink-faint">{{ $line->contactLens->brand }} &middot; {{ __('box of') }} {{ $line->contactLens->pack_size }}</p>
                                </div>
                                <p class="whitespace-nowrap font-mono text-sm text-ink">${{ number_format($pricing->contactLensLineTotal($line), 2) }}</p>
                            </div>

                            <div class="mt-3 flex items-center justify-between">
                                <form method="POST" action="{{ route('cart.contact-lenses.update', $line) }}" class="flex items-center gap-2">
                                    @csrf @method('PATCH')
                                    <label class="field-label !mb-0" for="qty-cl-{{ $line->id }}">{{ __('Qty') }}</label>
                                    <input type="number" id="qty-cl-{{ $line->id }}" name="quantity" value="{{ $line->quantity }}" min="1" max="20" class="input !w-16 !py-1.5 !text-xs">
                                    <button type="submit" class="btn-ghost btn-sm">{{ __('Update') }}</button>
                                </form>
                                <form method="POST" action="{{ route('cart.contact-lenses.destroy', $line) }}" onsubmit="return confirm('{{ __('Remove this item?') }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-danger hover:underline">{{ __('Remove') }}</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="panel h-fit p-6">
                <p class="eyebrow">{{ __('Summary') }}</p>
                <div class="mt-4 flex items-center justify-between text-sm">
                    <span class="text-ink-soft">{{ __('Subtotal') }}</span>
                    <span class="font-mono font-medium text-accent">${{ number_format($subtotal, 2) }}</span>
                </div>
                <p class="mt-1 text-xs text-ink-faint">{{ __('Shipping and any tax are calculated at checkout.') }}</p>
                <a href="{{ route('checkout.index') }}" class="btn-accent mt-5 w-full">{{ __('Checkout') }}</a>
            </div>
        </div>

        <x-product-rail
            :products="$alsoBought"
            :eyebrow="__('Before you check out')"
            :title="__('Goes well with your cart')"
        />
    @endif
</x-layout>
