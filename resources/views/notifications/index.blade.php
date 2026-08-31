<x-layout title="Inbox">
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="eyebrow">{{ __('Your account') }}</p>
            <h1 class="mt-1 font-display text-3xl font-semibold text-ink">{{ __('Inbox') }}</h1>
            <p class="mt-1 text-sm text-ink-soft">
                {{ __('Order updates, return decisions, prescription checks and review news.') }}
            </p>
        </div>

        @if ($notifications->isNotEmpty())
            <div class="flex items-center gap-3">
                @if ($unreadCount > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        <button type="submit" class="btn-outline btn-sm">{{ __('Mark all as read') }}</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('notifications.clear') }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-xs text-ink-faint hover:text-danger hover:underline">{{ __('Clear read') }}</button>
                </form>
            </div>
        @endif
    </div>

    @if ($notifications->isEmpty())
        <x-empty-state :title="__('Nothing here yet')"
                       :description="__('We\'ll write to you here when an order moves, a return is decided, or your prescription is verified.')">
            <x-slot:action>
                <a href="{{ route('frames.index') }}" class="btn-primary btn-sm">{{ __('Browse frames') }}</a>
            </x-slot:action>
        </x-empty-state>
    @else
        <x-notification-list :notifications="$notifications" />

        {{ $notifications->links() }}
    @endif
</x-layout>
