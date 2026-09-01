<x-admin-layout title="Messages" heading="Messages">
    <x-slot:subheading>{{ __('Enquiries sent from the About page.') }}</x-slot:subheading>

    <div class="mb-6 flex flex-wrap gap-2">
        @foreach ([
            'open' => __('Open'),
            'new' => __('Unread'),
            'all' => __('All'),
        ] as $key => $label)
            <a href="{{ route('admin.messages.index', ['filter' => $key]) }}" class="btn-sm {{ $filter === $key ? 'btn-primary' : 'btn-outline' }}">{{ $label }}</a>
        @endforeach
    </div>

    @if ($messages->isEmpty())
        <x-empty-state
            :title="__('No messages here')"
            :description="__('Anything sent through the contact form on the About page lands in this list.')"
        />
    @else
        <div class="space-y-4">
            @foreach ($messages as $message)
                <div class="panel p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="badge-accent">{{ __($message->topicLabel()) }}</span>
                                @if ($message->status === \App\Models\ContactMessage::STATUS_NEW)
                                    <span class="badge-warn">{{ __('Unread') }}</span>
                                @elseif ($message->status === \App\Models\ContactMessage::STATUS_CLOSED)
                                    <span class="badge-neutral">{{ __('Closed') }}</span>
                                @else
                                    <span class="badge-signal">{{ __('Read') }}</span>
                                @endif
                                @if ($message->user)
                                    <span class="badge-neutral">{{ __('Customer account') }}</span>
                                @endif
                            </div>

                            <p class="mt-2 text-sm font-medium text-ink">{{ $message->name }}</p>
                            <p class="font-mono text-[11px] text-ink-faint">
                                <a href="mailto:{{ $message->email }}" class="hover:text-ink">{{ $message->email }}</a>
                                @if ($message->phone)
                                    &middot; <a href="tel:{{ preg_replace('/[^\d+]/', '', $message->phone) }}" class="hover:text-ink">{{ $message->phone }}</a>
                                @endif
                            </p>
                        </div>

                        <p class="whitespace-nowrap font-mono text-[11px] text-ink-faint">{{ $message->created_at->format('M j, Y · H:i') }}</p>
                    </div>

                    <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-ink-soft">{{ $message->message }}</p>

                    <div class="hairline-top mt-4 flex flex-wrap items-center gap-4 pt-4">
                        <a
                            href="mailto:{{ $message->email }}?subject={{ rawurlencode(__('Re: :topic — Lucent Optics', ['topic' => __($message->topicLabel())])) }}"
                            class="btn-outline-accent btn-sm"
                        >{{ __('Reply by email') }}</a>

                        @foreach ([
                            \App\Models\ContactMessage::STATUS_READ => __('Mark read'),
                            \App\Models\ContactMessage::STATUS_CLOSED => __('Close'),
                            \App\Models\ContactMessage::STATUS_NEW => __('Reopen'),
                        ] as $status => $label)
                            @if ($message->status !== $status)
                                <form method="POST" action="{{ route('admin.messages.status', $message) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $status }}">
                                    <button type="submit" class="btn-ghost btn-sm !px-0">{{ $label }}</button>
                                </form>
                            @endif
                        @endforeach

                        <form method="POST" action="{{ route('admin.messages.destroy', $message) }}" onsubmit="return confirm('{{ __('Delete this message?') }}')" class="ml-auto">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-danger hover:underline">{{ __('Delete') }}</button>
                        </form>
                    </div>

                    @if ($message->handled_at)
                        <p class="mt-3 font-mono text-[10.5px] text-ink-faint">
                            {{ __('Last touched by :name on :date', [
                                'name' => $message->handler?->first_name ?? __('a colleague'),
                                'date' => $message->handled_at->format('M j, Y'),
                            ]) }}
                        </p>
                    @endif
                </div>
            @endforeach
        </div>

        {{ $messages->links() }}
    @endif
</x-admin-layout>
