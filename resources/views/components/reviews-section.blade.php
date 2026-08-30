@props(['reviews', 'reviewableType', 'reviewableId'])

<section class="hairline-top mt-16 pt-12">
    <div class="flex items-center justify-between">
        <h2 class="font-display text-xl font-semibold text-ink">{{ __('Reviews') }}</h2>
        <p class="font-mono text-xs text-ink-faint">{{ $reviews->count() }} {{ Str::plural('review', $reviews->count()) }}</p>
    </div>

    {{-- Staff moderate reviews from the admin console; they don't write them. --}}
    @auth
        @unless (auth()->user()->isAdmin())
        <details class="panel mt-6 p-5">
            <summary class="cursor-pointer select-none text-sm font-medium text-ink">{{ __('Write a review') }}</summary>
            <form method="POST" action="{{ route('reviews.store') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <input type="hidden" name="reviewable_type" value="{{ $reviewableType }}">
                <input type="hidden" name="reviewable_id" value="{{ $reviewableId }}">

                <div>
                    <label class="field-label">{{ __('Rating') }}</label>
                    <div class="flex gap-3">
                        @for ($i = 5; $i >= 1; $i--)
                            <label class="flex cursor-pointer items-center gap-1.5 text-sm text-ink-soft">
                                <input type="radio" name="rating" value="{{ $i }}" class="accent-ink" @checked($i === 5) required>
                                {{ $i }}
                            </label>
                        @endfor
                    </div>
                </div>

                <div>
                    <label class="field-label" for="review-title">{{ __('Title (optional)') }}</label>
                    <input type="text" id="review-title" name="title" class="input" maxlength="255">
                </div>

                <div>
                    <label class="field-label" for="review-body">{{ __('Your review (optional)') }}</label>
                    <textarea id="review-body" name="body" rows="4" class="textarea" maxlength="4000"></textarea>
                </div>

                <div>
                    <label class="field-label" for="review-images">{{ __('Photos (optional, up to 6)') }}</label>
                    <input type="file" id="review-images" name="images[]" accept="image/*" multiple class="input !py-1.5 file:mr-3 file:rounded-[3px] file:border-0 file:bg-ink file:px-3 file:py-1.5 file:text-xs file:text-white">
                </div>

                <button type="submit" class="btn-accent btn-sm">{{ __('Submit review') }}</button>
                <p class="text-xs text-ink-faint">{{ __('Reviews are checked by our team before they go live.') }}</p>
            </form>
        </details>
        @endunless
    @else
        <p class="mt-4 text-sm text-ink-soft">
            <a href="{{ route('login') }}" class="underline hover:text-ink">{{ __('Sign in') }}</a> {{ __('to write a review.') }}
        </p>
    @endauth

    @if ($reviews->isEmpty())
        <p class="mt-8 text-sm text-ink-faint">{{ __('No reviews yet — be the first.') }}</p>
    @else
        <div class="mt-8 space-y-8">
            @foreach ($reviews as $review)
                <article class="hairline-top pt-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <x-rating :value="$review->rating" />
                                @if ($review->is_verified_purchase)
                                    <span class="badge-signal">{{ __('Verified purchase') }}</span>
                                @endif
                            </div>
                            @if ($review->title)
                                <p class="mt-2 text-sm font-medium text-ink">{{ $review->title }}</p>
                            @endif
                        </div>
                        <p class="whitespace-nowrap font-mono text-[11px] text-ink-faint">{{ $review->user->first_name ?? __('Customer') }} &middot; {{ $review->created_at->format('M j, Y') }}</p>
                    </div>
                    @if ($review->body)
                        <p class="mt-2 text-sm leading-relaxed text-ink-soft">{{ $review->body }}</p>
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
                    @auth
                        @if ($review->user_id === auth()->id())
                            <form method="POST" action="{{ route('reviews.destroy', $review) }}" class="mt-2" onsubmit="return confirm('{{ __('Remove this review?') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-danger hover:underline">{{ __('Delete') }}</button>
                            </form>
                        @endif
                    @endauth
                </article>
            @endforeach
        </div>
    @endif
</section>
