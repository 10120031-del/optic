<x-layout title="Orders">
    <div class="mb-8">
        <p class="eyebrow">{{ __('Your account') }}</p>
        <h1 class="mt-1 font-display text-3xl font-semibold text-ink">{{ __('Orders') }}</h1>
    </div>

    @if ($orders->isEmpty())
        <x-empty-state :title="__('No orders yet')" :description="__('Once you place an order, you\'ll be able to track it here.')">
            <x-slot:action>
                <a href="{{ route('frames.index') }}" class="btn-primary btn-sm">{{ __('Start shopping') }}</a>
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="overflow-x-auto">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>{{ __('Order') }}</th>
                        <th>{{ __('Placed') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-right">{{ __('Total') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr>
                            <td class="font-mono text-ink">{{ $order->order_number }}</td>
                            <td>{{ $order->created_at->format('M j, Y') }}</td>
                            <td><x-status-badge :status="$order->status" /></td>
                            <td class="text-right font-mono text-ink">${{ number_format($order->total, 2) }}</td>
                            <td class="text-right"><a href="{{ route('orders.show', $order) }}" class="text-xs text-ink underline hover:no-underline">{{ __('View') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $orders->links() }}
    @endif
</x-layout>
