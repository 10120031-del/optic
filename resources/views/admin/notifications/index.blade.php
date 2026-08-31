<x-admin-layout title="Inbox" heading="Inbox">
    <x-slot:subheading>
        {{ __('New orders, returns to settle, reviews to moderate, prescriptions to verify and stock running out.') }}
    </x-slot:subheading>

    @if ($notifications->isNotEmpty())
        <div class="mb-6 flex items-center gap-3">
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="btn-outline btn-sm">{{ __('Mark all as read') }}</button>
                </form>
                <span class="font-mono text-[11px] uppercase tracking-wider text-ink-faint">
                    {{ trans_choice(':count unread|:count unread', $unreadCount, ['count' => $unreadCount]) }}
                </span>
            @endif
            <form method="POST" action="{{ route('notifications.clear') }}" class="ml-auto">
                @csrf @method('DELETE')
                <button type="submit" class="text-xs text-ink-faint hover:text-danger hover:underline">{{ __('Clear read') }}</button>
            </form>
        </div>
    @endif

    @if ($notifications->isEmpty())
        <x-empty-state :title="__('Nothing needs you right now')"
                       :description="__('New orders, return requests, pending reviews and low-stock warnings all arrive here.')" />
    @else
        <x-notification-list :notifications="$notifications" />

        {{ $notifications->links() }}
    @endif
</x-admin-layout>
