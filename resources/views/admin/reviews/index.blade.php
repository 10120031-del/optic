<x-admin-layout title="Reviews" heading="Reviews">
    <div class="mb-6 flex gap-2">
        <a href="{{ route('admin.reviews.index', ['filter' => 'pending']) }}" class="btn-sm {{ $filter === 'pending' ? 'btn-primary' : 'btn-outline' }}">{{ __('Pending') }}</a>
        <a href="{{ route('admin.reviews.index', ['filter' => 'all']) }}" class="btn-sm {{ $filter === 'all' ? 'btn-primary' : 'btn-outline' }}">{{ __('All') }}</a>
    </div>

    @if ($reviews->isEmpty())
        <x-empty-state :title="__('Nothing to review')" />
    @else
        <div class="space-y-4">
            @foreach ($reviews as $review)
                <div class="panel p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <x-rating :value="$review->rating" />
                                @if ($review->is_verified_purchase)
                                    <span class="badge-signal">{{ __('Verified purchase') }}</span>
                                @endif
                                @if ($review->is_approved)
                                    <span class="badge-neutral">{{ __('Approved') }}</span>
                                @endif
                            </div>
                            <p class="mt-1.5 text-sm text-ink">{{ $review->reviewable->name ?? __('Deleted product') }}</p>
                            @if ($review->title)
                                <p class="text-xs text-ink-soft">{{ $review->title }}</p>
                            @endif
                        </div>
                        <p class="whitespace-nowrap font-mono text-[11px] text-ink-faint">{{ $review->user->first_name ?? __('Customer') }} &middot; {{ $review->created_at->format('M j, Y') }}</p>
                    </div>

                    @if ($review->body)
                        <p class="mt-3 text-sm text-ink-soft">{{ $review->body }}</p>
                    @endif

                    @if ($review->images->isNotEmpty())
                        <div class="mt-3 flex gap-2">
                            @foreach ($review->images as $image)
                                <a href="{{ Storage::disk('public')->url($image->path) }}" target="_blank" class="block size-16 overflow-hidden border border-hairline">
                                    <img src="{{ Storage::disk('public')->url($image->path) }}" alt="" class="h-full w-full object-cover">
                                </a>
                            @endforeach
                        </div>
                    @endif

                    <div class="hairline-top mt-4 flex items-center gap-4 pt-4">
                        @unless ($review->is_approved)
                            <form method="POST" action="{{ route('admin.reviews.approve', $review) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn-outline btn-sm">{{ __('Approve') }}</button>
                            </form>
                        @endunless
                        <form method="POST" action="{{ route('admin.reviews.destroy', $review) }}" onsubmit="return confirm('{{ __('Remove this review?') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-danger hover:underline">{{ __('Delete') }}</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        {{ $reviews->links() }}
    @endif
</x-admin-layout>
