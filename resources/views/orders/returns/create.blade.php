<x-layout :title="'Return — '.$order->order_number">
    <nav class="mb-8 font-mono text-[11px] uppercase tracking-[0.06em] text-ink-faint">
        <a href="{{ route('orders.show', $order) }}" class="hover:text-ink">{{ $order->order_number }}</a>
        <span class="mx-1.5">/</span>
        <span class="text-ink-soft">{{ __('Return / Exchange') }}</span>
    </nav>

    <div class="mb-8">
        <p class="eyebrow">{{ __('Order') }} {{ $order->order_number }}</p>
        <h1 class="mt-1 font-display text-3xl font-semibold text-ink">{{ __('Request a return or exchange') }}</h1>
    </div>

    <form method="POST" action="{{ route('orders.returns.store', $order) }}" class="max-w-2xl space-y-8">
        @csrf

        <section>
            <p class="eyebrow mb-4">{{ __('What would you like?') }}</p>
            <div class="grid grid-cols-2 gap-3">
                <label class="flex cursor-pointer items-center gap-3 rounded-[3px] border border-hairline-strong p-3 has-[:checked]:border-ink has-[:checked]:bg-wash">
                    <input type="radio" name="type" value="return" class="accent-ink" checked required>
                    <span class="text-sm text-ink">{{ __('Return for refund') }}</span>
                </label>
                <label class="flex cursor-pointer items-center gap-3 rounded-[3px] border border-hairline-strong p-3 has-[:checked]:border-ink has-[:checked]:bg-wash">
                    <input type="radio" name="type" value="exchange" class="accent-ink" required>
                    <span class="text-sm text-ink">{{ __('Exchange') }}</span>
                </label>
            </div>
        </section>

        <section>
            <p class="eyebrow mb-4">{{ __('Items to include') }}</p>
            <div class="space-y-2.5">
                @foreach ($order->eyeglasses as $line)
                    <label class="flex items-start gap-3 rounded-[3px] border border-hairline p-3 has-[:checked]:border-ink has-[:checked]:bg-wash">
                        <input type="checkbox" name="items[{{ $loop->index }}][id]" value="{{ $line->id }}" class="checkbox mt-1" onchange="this.closest('label').querySelectorAll('input:not([type=checkbox])').forEach(el => el.disabled = !this.checked)">
                        <input type="hidden" name="items[{{ $loop->index }}][type]" value="eyeglass" disabled>
                        <span class="flex-1">
                            <span class="block text-sm text-ink">{{ $line->frame_name }} &middot; {{ $line->lens_name }}</span>
                            <span class="mt-2 flex items-center gap-2">
                                <span class="text-xs text-ink-faint">{{ __('Qty to return') }}</span>
                                <input type="number" name="items[{{ $loop->index }}][quantity]" value="1" min="1" max="{{ $line->quantity }}" disabled class="input !w-16 !py-1 !text-xs">
                            </span>
                        </span>
                    </label>
                @endforeach
                @foreach ($order->contactLenses as $line)
                    <label class="flex items-start gap-3 rounded-[3px] border border-hairline p-3 has-[:checked]:border-ink has-[:checked]:bg-wash">
                        <input type="checkbox" name="items[{{ $loop->index + $order->eyeglasses->count() }}][id]" value="{{ $line->id }}" class="checkbox mt-1" onchange="this.closest('label').querySelectorAll('input:not([type=checkbox])').forEach(el => el.disabled = !this.checked)">
                        <input type="hidden" name="items[{{ $loop->index + $order->eyeglasses->count() }}][type]" value="contact_lens" disabled>
                        <span class="flex-1">
                            <span class="block text-sm text-ink">{{ $line->product_name }}</span>
                            <span class="mt-2 flex items-center gap-2">
                                <span class="text-xs text-ink-faint">{{ __('Qty to return') }}</span>
                                <input type="number" name="items[{{ $loop->index + $order->eyeglasses->count() }}][quantity]" value="1" min="1" max="{{ $line->quantity }}" disabled class="input !w-16 !py-1 !text-xs">
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
        </section>

        <section>
            <label class="field-label" for="reason">{{ __('Reason') }}</label>
            <select id="reason" name="reason" class="select" required>
                <option value="wrong_prescription">{{ __('Wrong prescription') }}</option>
                <option value="wrong_size_fit">{{ __('Wrong size / fit') }}</option>
                <option value="damaged_or_defective">{{ __('Damaged or defective') }}</option>
                <option value="not_as_described">{{ __('Not as described') }}</option>
                <option value="changed_mind">{{ __('Changed my mind') }}</option>
                <option value="other">{{ __('Other') }}</option>
            </select>
        </section>

        <section>
            <label class="field-label" for="reason_details">{{ __('Tell us more (optional)') }}</label>
            <textarea id="reason_details" name="reason_details" rows="4" class="textarea" maxlength="2000"></textarea>
        </section>

        <button type="submit" class="btn-primary w-full">{{ __('Submit request') }}</button>
    </form>
</x-layout>
