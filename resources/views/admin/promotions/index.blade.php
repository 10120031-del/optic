<x-admin-layout title="Promotions" heading="Promotions">
    <div class="mb-6 flex justify-end">
        <a href="{{ route('admin.promotions.create') }}" class="btn-primary btn-sm">{{ __('New campaign') }}</a>
    </div>

    @if ($campaigns->isEmpty())
        <x-empty-state :title="__('No campaigns sent yet')" :description="__('Send your first promotional email to customers.')">
            <x-slot:action><a href="{{ route('admin.promotions.create') }}" class="btn-primary btn-sm">{{ __('New campaign') }}</a></x-slot:action>
        </x-empty-state>
    @else
        <div class="overflow-x-auto">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>{{ __('Campaign') }}</th>
                        <th>{{ __('Audience') }}</th>
                        <th class="text-right">{{ __('Recipients') }}</th>
                        <th>{{ __('Sent') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($campaigns as $campaign)
                        <tr>
                            <td class="text-ink">{{ $campaign->title }}</td>
                            <td>{{ str($campaign->audience)->replace('_', ' ')->headline() }}</td>
                            <td class="text-right font-mono">{{ $campaign->recipients_count }}</td>
                            <td>{{ $campaign->sent_at?->format('M j, Y g:ia') ?? '—' }}</td>
                            <td class="text-right"><a href="{{ route('admin.promotions.show', $campaign) }}" class="text-xs text-ink underline hover:no-underline">{{ __('View') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $campaigns->links() }}
    @endif
</x-admin-layout>
