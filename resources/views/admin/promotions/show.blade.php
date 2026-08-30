<x-admin-layout :title="$campaign->title" :heading="$campaign->title">
    <nav class="-mt-4 mb-8 font-mono text-[11px] uppercase tracking-[0.06em] text-ink-faint">
        <a href="{{ route('admin.promotions.index') }}" class="hover:text-ink">{{ __('Promotions') }}</a>
        <span class="mx-1.5">/</span>
        <span class="text-ink-soft">{{ $campaign->title }}</span>
    </nav>

    <div class="grid grid-cols-1 gap-10 lg:grid-cols-[1fr_300px]">
        <div>
            <p class="eyebrow mb-2">{{ __('Subject') }}</p>
            <p class="mb-6 text-sm text-ink">{{ $campaign->subject }}</p>

            <p class="eyebrow mb-2">{{ __('Body') }}</p>
            <div class="panel whitespace-pre-line p-5 text-sm leading-relaxed text-ink-soft">{{ $campaign->body }}</div>
        </div>

        <div class="panel h-fit p-6">
            <p class="eyebrow mb-4">{{ __('Delivery') }}</p>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-ink-soft">{{ __('Audience') }}</dt><dd class="text-ink">{{ str($campaign->audience)->replace('_', ' ')->headline() }}</dd></div>
                <div class="flex justify-between"><dt class="text-ink-soft">{{ __('Recipients') }}</dt><dd class="font-mono text-ink">{{ $campaign->recipients_count }}</dd></div>
                <div class="flex justify-between"><dt class="text-ink-soft">{{ __('Sent') }}</dt><dd class="text-ink">{{ $campaign->sent_at?->format('M j, Y g:ia') ?? '—' }}</dd></div>
                @if ($campaign->createdBy)
                    <div class="flex justify-between"><dt class="text-ink-soft">{{ __('Sent by') }}</dt><dd class="text-ink">{{ $campaign->createdBy->first_name }}</dd></div>
                @endif
            </dl>
        </div>
    </div>
</x-admin-layout>
