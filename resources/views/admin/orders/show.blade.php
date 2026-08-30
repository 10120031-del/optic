<x-admin-layout :title="$order->order_number" :heading="$order->order_number">
    <nav class="-mt-4 mb-8 font-mono text-[11px] uppercase tracking-[0.06em] text-ink-faint">
        <a href="{{ route('admin.orders.index') }}" class="hover:text-ink">{{ __('Orders') }}</a>
        <span class="mx-1.5">/</span>
        <span class="text-ink-soft">{{ $order->order_number }}</span>
    </nav>

    <div class="grid grid-cols-1 gap-10 lg:grid-cols-[1fr_340px]">
        <div class="space-y-8">
            <div class="panel p-5">
                <p class="eyebrow">{{ __('Customer') }}</p>
                <p class="mt-1 text-sm text-ink">{{ $order->user->first_name }} {{ $order->user->last_name }}</p>
                <p class="text-xs text-ink-faint">{{ $order->user->email }}</p>
            </div>

            <section>
                <p class="eyebrow mb-4">{{ __('Items') }}</p>
                <div class="space-y-3">
                    @foreach ($order->eyeglasses as $line)
                        <div class="panel p-4">
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
                                    @if ($line->left_sphere !== null || $line->right_sphere !== null)
                                        <p class="mt-2 font-mono text-[11px] text-ink-faint">OS {{ $line->left_sphere }}/{{ $line->left_cylinder }}&times;{{ $line->left_axis }} &middot; OD {{ $line->right_sphere }}/{{ $line->right_cylinder }}&times;{{ $line->right_axis }}</p>
                                    @endif
                                </div>
                                <span class="whitespace-nowrap font-mono text-sm text-ink">${{ number_format($line->line_total, 2) }}</span>
                            </div>
                        </div>
                    @endforeach
                    @foreach ($order->contactLenses as $line)
                        <div class="panel p-4">
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
                <section>
                    <p class="eyebrow mb-4">{{ __('Returns & exchanges') }}</p>
                    <div class="space-y-2">
                        @foreach ($order->returns as $return)
                            <a href="{{ route('admin.returns.show', $return) }}" class="panel flex items-center justify-between p-4 hover:border-ink">
                                <span class="text-sm text-ink">{{ str($return->type)->headline() }} &middot; {{ $return->items->count() }} {{ Str::plural('item', $return->items->count()) }}</span>
                                <x-status-badge :status="$return->status" />
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            <section>
                <p class="eyebrow mb-4">{{ __('Status timeline') }}</p>
                <ol class="space-y-4">
                    @foreach ($order->statusHistory as $event)
                        <li class="flex items-start gap-3 text-sm">
                            <span class="mt-1 size-1.5 shrink-0 rounded-full bg-ink"></span>
                            <div>
                                <span class="capitalize text-ink">{{ str($event->status)->replace('_', ' ') }}</span>
                                @if ($event->note)<span class="text-ink-soft"> — {{ $event->note }}</span>@endif
                                <span class="ml-2 font-mono text-[11px] text-ink-faint">{{ $event->created_at->format('M j, g:ia') }} @if($event->changedBy) &middot; {{ $event->changedBy->first_name }} @endif</span>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>

        <div class="space-y-6">
            <div class="panel p-6">
                <p class="eyebrow mb-4">{{ __('Totals') }}</p>
                <div class="space-y-1.5 text-sm">
                    <div class="flex justify-between"><span class="text-ink-soft">{{ __('Subtotal') }}</span><span class="font-mono text-ink">${{ number_format($order->subtotal, 2) }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-soft">{{ __('Shipping') }}</span><span class="font-mono text-ink">${{ number_format($order->shipping_cost, 2) }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-soft">{{ __('Tax') }}</span><span class="font-mono text-ink">${{ number_format($order->tax, 2) }}</span></div>
                    <div class="hairline-top flex justify-between pt-2 font-medium"><span class="text-ink">{{ __('Total') }}</span><span class="font-mono text-ink">${{ number_format($order->total, 2) }}</span></div>
                </div>
                @if ($order->payments->isNotEmpty())
                    <div class="hairline-top mt-4 pt-4 space-y-1.5">
                        @foreach ($order->payments as $payment)
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-ink-soft">{{ str($payment->method)->replace('_', ' ')->title() }}</span>
                                <x-status-badge :status="$payment->status" />
                            </div>
                        @endforeach
                        @if ($order->payments->contains('status', \App\Models\Payment::STATUS_PENDING))
                            <p class="text-xs text-ink-faint">
                                {{ __('Cash still to collect. Setting the status below to Paid or Delivered records it as received.') }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            <div class="panel p-6 text-xs text-ink-soft">
                <p class="field-label !mb-1">{{ __('Ship to') }}</p>
                <p>{{ $order->shipping_address_line }}</p>
                <p>{{ $order->shipping_city }}{{ $order->shipping_postal_code ? ', '.$order->shipping_postal_code : '' }}</p>
                <p>{{ $order->shipping_country }}</p>
            </div>

            <div class="panel p-6">
                <p class="eyebrow mb-4">{{ __('Update status') }}</p>
                <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="space-y-3">
                    @csrf @method('PATCH')
                    <select name="status" class="select">
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected($order->status === $status)>{{ str($status)->headline() }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="carrier" value="{{ $order->carrier }}" placeholder="{{ __('Carrier') }}" class="input !py-2 !text-xs">
                    <input type="text" name="tracking_number" value="{{ $order->tracking_number }}" placeholder="{{ __('Tracking number') }}" class="input !py-2 !text-xs">
                    <input type="date" name="estimated_delivery_date" value="{{ $order->estimated_delivery_date?->format('Y-m-d') }}" class="input !py-2 !text-xs">
                    <textarea name="note" rows="2" placeholder="{{ __('Note (optional)') }}" class="textarea !py-2 !text-xs"></textarea>
                    <button type="submit" class="btn-primary btn-sm w-full">{{ __('Update order') }}</button>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
