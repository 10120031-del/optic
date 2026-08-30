<x-admin-layout title="Returns" heading="Returns & Exchanges">
    <form method="GET" action="{{ route('admin.returns.index') }}" class="mb-6 flex flex-wrap items-center gap-3">
        <select name="status" class="select max-w-xs" onchange="this.form.submit()">
            <option value="">{{ __('All statuses') }}</option>
            @foreach (['requested', 'approved', 'rejected', 'item_received', 'refunded', 'exchanged'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->headline() }}</option>
            @endforeach
        </select>
    </form>

    @if ($returns->isEmpty())
        <x-empty-state :title="__('No return requests found')" />
    @else
        <div class="overflow-x-auto">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>{{ __('Order') }}</th>
                        <th>{{ __('Customer') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Reason') }}</th>
                        <th>{{ __('Requested') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($returns as $return)
                        <tr>
                            <td class="font-mono text-ink">{{ $return->order->order_number }}</td>
                            <td>{{ $return->order->user->first_name }} {{ $return->order->user->last_name }}</td>
                            <td>{{ str($return->type)->headline() }}</td>
                            <td>{{ str($return->reason)->replace('_', ' ') }}</td>
                            <td>{{ $return->created_at->format('M j, Y') }}</td>
                            <td><x-status-badge :status="$return->status" /></td>
                            <td class="text-right"><a href="{{ route('admin.returns.show', $return) }}" class="text-xs text-ink underline hover:no-underline">{{ __('View') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $returns->links() }}
    @endif
</x-admin-layout>
