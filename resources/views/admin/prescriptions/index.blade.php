<x-admin-layout title="Prescriptions" heading="Prescriptions">
    <div class="mb-6 flex gap-2">
        <a href="{{ route('admin.prescriptions.index', ['filter' => 'unverified']) }}" class="btn-sm {{ $filter === 'unverified' ? 'btn-primary' : 'btn-outline' }}">{{ __('Unverified') }}</a>
        <a href="{{ route('admin.prescriptions.index', ['filter' => 'all']) }}" class="btn-sm {{ $filter === 'all' ? 'btn-primary' : 'btn-outline' }}">{{ __('All') }}</a>
    </div>

    @if ($prescriptions->isEmpty())
        <x-empty-state :title="__('Nothing to review')" />
    @else
        <div class="overflow-x-auto">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>{{ __('Customer') }}</th>
                        <th>{{ __('Doctor') }}</th>
                        <th>{{ __('Submitted') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($prescriptions as $prescription)
                        <tr>
                            <td class="text-ink">{{ $prescription->user->first_name }} {{ $prescription->user->last_name }}</td>
                            <td>{{ $prescription->doctor_name ?? '—' }}</td>
                            <td>{{ $prescription->created_at->format('M j, Y') }}</td>
                            <td>
                                @if ($prescription->is_verified)
                                    <span class="badge-signal">{{ __('Verified') }}</span>
                                @else
                                    <span class="badge-neutral">{{ __('Unverified') }}</span>
                                @endif
                            </td>
                            <td class="text-right"><a href="{{ route('admin.prescriptions.show', $prescription) }}" class="text-xs text-ink underline hover:no-underline">{{ __('Review') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $prescriptions->links() }}
    @endif
</x-admin-layout>
