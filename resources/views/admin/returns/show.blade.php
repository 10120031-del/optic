<x-admin-layout title="Return Request" :heading="'Return — '.$return->order->order_number">
    <nav class="-mt-4 mb-8 font-mono text-[11px] uppercase tracking-[0.06em] text-ink-faint">
        <a href="{{ route('admin.returns.index') }}" class="hover:text-ink">{{ __('Returns') }}</a>
        <span class="mx-1.5">/</span>
        <span class="text-ink-soft">#{{ $return->id }}</span>
    </nav>

    <div class="grid grid-cols-1 gap-10 lg:grid-cols-[1fr_340px]">
        <div class="space-y-8">
            <div class="panel p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="eyebrow">{{ __('Order') }}</p>
                        <a href="{{ route('admin.orders.show', $return->order) }}" class="text-sm text-ink underline hover:no-underline">{{ $return->order->order_number }}</a>
                    </div>
                    <x-status-badge :status="$return->status" />
                </div>
                <p class="mt-3 text-sm text-ink-soft">{{ $return->requestedBy->first_name }} {{ $return->requestedBy->last_name }} &middot; {{ $return->created_at->format('M j, Y') }}</p>
                <p class="mt-2 text-sm text-ink">{{ str($return->type)->headline() }} &middot; {{ str($return->reason)->replace('_', ' ') }}</p>
                @if ($return->reason_details)
                    <p class="mt-2 text-sm text-ink-soft">{{ $return->reason_details }}</p>
                @endif
            </div>

            <section>
                <p class="eyebrow mb-4">{{ __('Items') }}</p>
                <div class="space-y-2">
                    @foreach ($return->items as $item)
                        <div class="panel flex items-center justify-between p-4 text-sm">
                            <span class="text-ink">
                                @if (str($item->returnable_type)->contains('OrderEyeglass'))
                                    {{ $item->returnable?->frame_name ?? __('Eyeglass line') }}
                                @else
                                    {{ $item->returnable?->product_name ?? __('Contact lens line') }}
                                @endif
                                <span class="text-ink-faint">&times;{{ $item->quantity }}</span>
                            </span>
                            @if ($item->condition_notes)
                                <span class="text-xs text-ink-faint">{{ $item->condition_notes }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            @if ($return->staff_notes)
                <section>
                    <p class="eyebrow mb-2">{{ __('Staff notes') }}</p>
                    <p class="text-sm text-ink-soft">{{ $return->staff_notes }}</p>
                </section>
            @endif
        </div>

        <div class="panel h-fit p-6">
            <p class="eyebrow mb-4">{{ __('Resolve') }}</p>
            <form method="POST" action="{{ route('admin.returns.status', $return) }}" class="space-y-3">
                @csrf @method('PATCH')
                <select name="status" class="select">
                    @foreach (['requested', 'approved', 'rejected', 'item_received', 'refunded', 'exchanged'] as $status)
                        <option value="{{ $status }}" @selected($return->status === $status)>{{ str($status)->headline() }}</option>
                    @endforeach
                </select>
                <div>
                    <label class="field-label" for="refund_amount">{{ __('Refund amount (if refunding)') }}</label>
                    <input type="number" step="0.01" min="0" id="refund_amount" name="refund_amount" value="{{ $return->refund_amount }}" class="input !py-2 !text-xs">
                </div>
                <div>
                    <label class="field-label" for="exchange_order_id">{{ __('Exchange order ID (if exchanging)') }}</label>
                    <input type="number" id="exchange_order_id" name="exchange_order_id" value="{{ $return->exchange_order_id }}" class="input !py-2 !text-xs">
                </div>
                <div>
                    <label class="field-label" for="staff_notes">{{ __('Staff notes') }}</label>
                    <textarea id="staff_notes" name="staff_notes" rows="3" class="textarea !py-2 !text-xs">{{ $return->staff_notes }}</textarea>
                </div>
                <button type="submit" class="btn-primary btn-sm w-full">{{ __('Update return') }}</button>
            </form>
        </div>
    </div>
</x-admin-layout>
