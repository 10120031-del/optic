@props(['notifications'])

{{--
    One inbox rendering for both sides of the shop — the storefront page and
    the staff console page differ only in the layout wrapped around this.

    Each row is a form rather than a link: opening a notification marks it
    read, which is a state change and so wants a POST and a CSRF token. The
    button holds only spans, since a <button> may not contain block elements,
    and the delete form sits alongside it rather than inside it.
--}}

<div class="divide-y divide-hairline border-y border-hairline">
    @foreach ($notifications as $notification)
        @php
            $data = $notification->data;
            $accent = match ($data['level'] ?? 'info') {
                'success' => 'bg-signal',
                'warn' => 'bg-warn',
                'danger' => 'bg-danger',
                default => 'bg-ink-faint',
            };
            $isUnread = $notification->read_at === null;
        @endphp

        <div class="flex items-start gap-3 py-4 {{ $isUnread ? '' : 'opacity-60' }}">
            <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $isUnread ? $accent : 'bg-transparent border border-hairline-strong' }}"
                  aria-hidden="true"></span>

            <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="min-w-0 flex-1">
                @csrf
                <button type="submit" class="block w-full text-left">
                    <span class="block text-sm {{ $isUnread ? 'font-medium text-ink' : 'text-ink-soft' }}">
                        {{ $data['title'] ?? __('Notification') }}
                    </span>
                    @if (! empty($data['body']))
                        <span class="mt-0.5 block text-sm text-ink-soft">{{ $data['body'] }}</span>
                    @endif
                    <span class="mt-1 block font-mono text-[10.5px] uppercase tracking-wider text-ink-faint">
                        {{ $notification->created_at->diffForHumans() }}
                        @if ($isUnread)
                            &middot; {{ __('Unread') }}
                        @endif
                    </span>
                </button>
            </form>

            <form method="POST" action="{{ route('notifications.destroy', $notification->id) }}" class="shrink-0">
                @csrf @method('DELETE')
                <button type="submit" class="text-xs text-ink-faint hover:text-danger hover:underline" aria-label="{{ __('Remove notification') }}">
                    {{ __('Remove') }}
                </button>
            </form>
        </div>
    @endforeach
</div>
