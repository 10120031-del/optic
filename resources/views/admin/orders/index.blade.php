<x-admin-layout title="Orders" heading="Orders">
    <form method="GET" action="{{ route($orderRoutes['index']) }}" class="mb-6 flex flex-wrap items-center gap-3">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Order number…') }}" class="input max-w-xs">
        <select name="status" class="select max-w-xs" onchange="this.form.submit()">
            <option value="">{{ __('All statuses') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->headline() }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-outline btn-sm">{{ __('Filter') }}</button>
    </form>

    @if ($orders->isEmpty())
        <x-empty-state :title="__('No orders found')" />
    @else
        <div class="overflow-x-auto">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>{{ __('Order') }}</th>
                        <th>{{ __('Customer') }}</th>
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
                            <td>{{ $order->user->first_name }} {{ $order->user->last_name }}</td>
                            <td>{{ $order->created_at->format('M j, Y') }}</td>
                            <td><x-status-badge :status="$order->status" /></td>
                            <td class="text-right font-mono text-ink">${{ number_format($order->total, 2) }}</td>
                            <td class="text-right"><a href="{{ route($orderRoutes['show'], $order) }}" class="text-xs text-ink underline hover:no-underline">{{ __('View') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $orders->links() }}
    @endif
</x-admin-layout>
