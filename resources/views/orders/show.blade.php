<x-layout :title="'Order '.$order->order_number">
    <nav class="mb-8 font-mono text-[11px] uppercase tracking-[0.06em] text-ink-faint">
        <a href="{{ route('orders.index') }}" class="hover:text-ink">{{ __('Orders') }}</a>
        <span class="mx-1.5">/</span>
        <span class="text-ink-soft">{{ $order->order_number }}</span>
    </nav>

    <div class="mb-10 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="eyebrow">{{ __('Order') }}</p>
            <h1 class="mt-1 font-display text-3xl font-semibold text-ink">{{ $order->order_number }}</h1>
            <p class="mt-1 text-sm text-ink-soft">{{ __('Placed') }} {{ $order->created_at->format('F j, Y') }}</p>
        </div>
        <div class="text-right">
            <x-status-badge :status="$order->status" />
            @if (in_array($order->status, ['delivered', 'shipped'], true))
                <a href="{{ route('orders.returns.create', $order) }}" class="mt-3 block text-xs text-ink underline hover:no-underline">{{ __('Request a return / exchange') }}</a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-12 lg:grid-cols-[1fr_320px]">
        <div class="space-y-10">
            {{-- Timeline --}}
            <section>
                <p class="eyebrow mb-4">{{ __('Tracking') }}</p>
                <ol class="space-y-5">
                    @foreach ($order->statusHistory as $event)
                        <li class="flex gap-4">
                            <div class="flex flex-col items-center">
                                <span class="mt-1 size-2 rounded-full {{ $loop->last ? 'bg-signal' : 'bg-ink' }}"></span>
                                @unless ($loop->last)
                                    <span class="mt-1 w-px flex-1 bg-hairline-strong"></span>
                                @endunless
                            </div>
                            <div class="pb-1">
                                <p class="text-sm font-medium capitalize text-ink">{{ str($event->status)->replace('_', ' ') }}</p>
                                @if ($event->note)
                                    <p class="text-xs text-ink-soft">{{ $event->note }}</p>
                                @endif
                                <p class="mt-0.5 font-mono text-[11px] text-ink-faint">{{ $event->created_at->format('M j, Y g:ia') }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
                @if ($order->carrier || $order->tracking_number)
                    <p class="mt-4 text-xs text-ink-soft">{{ __('Carrier') }}: {{ $order->carrier ?? '—' }} &middot; {{ __('Tracking') }}: {{ $order->tracking_number ?? '—' }}</p>
                @endif
                @if ($order->estimated_delivery_date)
                    <p class="mt-1 text-xs text-ink-soft">{{ __('Estimated delivery') }}: {{ $order->estimated_delivery_date->format('F j, Y') }}</p>
                @endif
            </section>

            {{-- Items --}}
            <section class="hairline-top pt-8">
                <p class="eyebrow mb-4">{{ __('Items') }}</p>
                <div class="space-y-4">
                    @foreach ($order->eyeglasses as $line)
                        <div class="panel p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-medium text-ink">{{ $line->frame_name }} <span class="text-ink-faint">&times;{{ $line->quantity }}</span></p>
                                    <p class="text-xs text-ink-faint">{{ $line->frame_brand }} &middot; {{ $line->lens_name }}</p>
                                    @if ($line->features->isNotEmpty())
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            @foreach ($line->features as $feature)
                                                <span class="badge-neutral">{{ $feature->feature_name }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <span class="whitespace-nowrap font-mono text-sm text-ink">${{ number_format($line->line_total, 2) }}</span>
                            </div>
                        </div>
                    @endforeach
                    @foreach ($order->contactLenses as $line)
                        <div class="panel p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-medium text-ink">{{ $line->product_name }} <span class="text-ink-faint">&times;{{ $line->quantity }}</span></p>
                                    <p class="text-xs text-ink-faint">{{ $line->brand }}</p>
                                </div>
                                <span class="whitespace-nowrap font-mono text-sm text-ink">${{ number_format($line->line_total, 2) }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            @if ($order->returns->isNotEmpty())
                <section class="hairline-top pt-8">
                    <p class="eyebrow mb-4">{{ __('Returns & exchanges') }}</p>
                    <div class="space-y-3">
                        @foreach ($order->returns as $return)
                            <div class="panel flex items-center justify-between p-4">
                                <div>
                                    <p class="text-sm text-ink">{{ str($return->type)->headline() }} &middot; {{ str($return->reason)->replace('_', ' ') }}</p>
                                    <p class="text-xs text-ink-faint">{{ $return->created_at->format('M j, Y') }} &middot; {{ $return->items->count() }} {{ Str::plural('item', $return->items->count()) }}</p>
                                </div>
                                <x-status-badge :status="$return->status" />
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        <div class="panel h-fit p-6">
            <p class="eyebrow mb-4">{{ __('Summary') }}</p>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-ink-soft">{{ __('Subtotal') }}</span><span class="font-mono text-ink">${{ number_format($order->subtotal, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-ink-soft">{{ __('Shipping') }}</span><span class="font-mono text-ink">${{ number_format($order->shipping_cost, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-ink-soft">{{ __('Tax') }}</span><span class="font-mono text-ink">${{ number_format($order->tax, 2) }}</span></div>
                <div class="hairline-top flex justify-between pt-2 text-base font-medium"><span class="text-ink">{{ __('Total') }}</span><span class="font-mono text-ink">${{ number_format($order->total, 2) }}</span></div>
            </div>

            <div class="hairline-top mt-5 pt-5 text-xs text-ink-soft">
                <p class="field-label !mb-1">{{ __('Ship to') }}</p>
                <p>{{ $order->shipping_address_line }}</p>
                <p>{{ $order->shipping_city }}{{ $order->shipping_postal_code ? ', '.$order->shipping_postal_code : '' }}</p>
                <p>{{ $order->shipping_country }}</p>
            </div>

            @if ($order->payments->isNotEmpty())
                <div class="hairline-top mt-5 pt-5">
                    <p class="field-label !mb-2">{{ __('Payments') }}</p>
                    @foreach ($order->payments as $payment)
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-ink-soft">{{ str($payment->method)->replace('_', ' ') }}</span>
                            <x-status-badge :status="$payment->status" />
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layout>
