<x-layout>
    {{-- Hero --}}
    <section class="relative -mx-6 overflow-hidden border-b border-hairline px-6 pb-16 pt-4 sm:pb-24">
        <div
            class="pointer-events-none absolute inset-0 -z-10"
            style="background-image: linear-gradient(var(--color-hairline) 1px, transparent 1px), linear-gradient(90deg, var(--color-hairline) 1px, transparent 1px); background-size: 44px 44px; mask-image: linear-gradient(to bottom, black, transparent 85%);"
        ></div>
        <div class="pointer-events-none absolute -right-24 -top-24 -z-10 size-[420px] animate-float-slow bg-accent/25 sm:size-[520px]" style="clip-path: polygon(20% 0, 100% 0, 100% 80%, 80% 100%, 0 100%, 0 20%);"></div>
        <div class="pointer-events-none absolute -bottom-32 left-1/3 -z-10 size-64 animate-float-slow bg-pop/25" style="clip-path: polygon(50% 0, 100% 50%, 50% 100%, 0 50%); animation-delay: -3s;"></div>

        <div class="mx-auto grid max-w-7xl grid-cols-1 items-center gap-12 pt-10 lg:grid-cols-[1.1fr_0.9fr] lg:gap-8">
            <div>
                <p class="badge-accent">
                    <span class="size-1.5 animate-pulse-dot rounded-full bg-accent"></span>
                    {{ __('New season · AI-fitted eyewear') }}
                </p>
                <h1 class="mt-5 max-w-xl font-display text-4xl font-semibold leading-[1.05] tracking-tight text-ink sm:text-5xl lg:text-6xl">
                    {{ __('See sharper.') }}
                    <span class="relative inline-block whitespace-nowrap text-accent">
                        {{ __('Look sharper.') }}
                        <svg class="absolute -bottom-1 left-0 w-full text-pop" viewBox="0 0 200 10" preserveAspectRatio="none" fill="none">
                            <path d="M1 7.5C40 2.5 160 2.5 199 7.5" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                        </svg>
                    </span>
                </h1>
                <p class="mt-6 max-w-md text-base leading-relaxed text-ink-soft">
                    {{ __('Precision frames and contact lenses, matched to your face shape by AI and cut to your exact prescription. No guesswork, no showroom small talk.') }}
                </p>

                <div class="mt-8 flex flex-wrap items-center gap-4">
                    <a href="{{ route('frames.index') }}" class="btn-accent">
                        {{ __('Shop now') }}
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </a>
                    <a href="{{ route('face-match.create') }}" class="btn-outline-accent">
                        {{ __('Try AI Face Match') }}
                    </a>
                </div>

                <div class="mt-12 flex flex-wrap gap-x-10 gap-y-4 font-mono text-[11px] uppercase tracking-[0.08em] text-ink-faint">
                    <div>
                        <span class="block font-display text-2xl font-semibold tracking-tight text-ink">{{ $frameCount }}+</span>
                        {{ __('Frames in stock') }}
                    </div>
                    <div>
                        <span class="block font-display text-2xl font-semibold tracking-tight text-ink">{{ $contactLensCount }}+</span>
                        {{ __('Contact lens lines') }}
                    </div>
                    <div>
                        @if ($reviewCount > 0)
                            <span class="block font-display text-2xl font-semibold tracking-tight text-accent">{{ $reviewCount }}+</span>
                            {{ __('Verified reviews') }}
                        @else
                            <span class="block font-display text-2xl font-semibold tracking-tight text-accent">30-day</span>
                            {{ __('Free returns') }}
                        @endif
                    </div>
                </div>
            </div>

            <div class="tick-frame-accent relative mx-auto aspect-square w-full max-w-md border border-hairline bg-accent-soft">
                <div class="absolute inset-6 flex animate-float-slow items-center justify-center">
                    <svg viewBox="0 0 120 60" class="w-full text-ink" fill="none" stroke="currentColor" stroke-width="2.2">
                        <circle cx="34" cy="30" r="24" />
                        <circle cx="86" cy="30" r="24" />
                        <path d="M58 30h4" stroke-linecap="round" />
                        <path d="M10 30c0-6 4-10 8-10" stroke-linecap="round" />
                        <path d="M110 30c0-6-4-10-8-10" stroke-linecap="round" />
                    </svg>
                </div>
                <span class="badge-pop absolute -bottom-3 left-6 bg-white">{{ __('AI-matched fit') }}</span>
                <span class="badge-accent absolute -top-3 right-6 bg-white">{{ __('Rx ready') }}</span>
            </div>
        </div>
    </section>

    {{-- Category tiles --}}
    <section class="mt-16">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <a href="{{ route('frames.index') }}" class="group tick-frame reveal relative overflow-hidden border border-hairline bg-accent-soft p-6 transition-all duration-200 hover:-translate-y-1 hover:border-accent hover:shadow-[5px_5px_0_var(--color-accent)]">
                <span class="eyebrow">{{ __('Category') }}</span>
                <h3 class="mt-2 font-display text-xl font-semibold text-ink">{{ __('Eyeglasses') }}</h3>
                <p class="mt-1 text-sm text-ink-soft">{{ __('Frames for every face shape, sized and styled.') }}</p>
                <span class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-accent">
                    {{ __('Shop frames') }}
                    <svg class="size-3.5 transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </span>
            </a>
            <a href="{{ route('contact-lenses.index') }}" class="group tick-frame reveal relative overflow-hidden border border-hairline bg-pop-soft p-6 transition-all duration-200 hover:-translate-y-1 hover:border-accent hover:shadow-[5px_5px_0_var(--color-pop)]">
                <span class="eyebrow">{{ __('Category') }}</span>
                <h3 class="mt-2 font-display text-xl font-semibold text-ink">{{ __('Contact Lenses') }}</h3>
                <p class="mt-1 text-sm text-ink-soft">{{ __('Daily, monthly, and toric lenses, all prescriptions.') }}</p>
                <span class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-pop">
                    {{ __('Shop lenses') }}
                    <svg class="size-3.5 transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </span>
            </a>
            <a href="{{ route('face-match.create') }}" class="group tick-frame reveal relative overflow-hidden border border-hairline bg-ink p-6 text-white transition-all duration-200 hover:-translate-y-1 hover:border-accent hover:shadow-[5px_5px_0_var(--color-accent)]">
                <span class="font-mono text-[11px] uppercase tracking-[0.12em] text-white/60">{{ __('AI Tool') }}</span>
                <h3 class="mt-2 font-display text-xl font-semibold">{{ __('Face Match') }}</h3>
                <p class="mt-1 text-sm text-white/70">{{ __('Upload a photo, get frames built for your shape.') }}</p>
                <span class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-pop">
                    {{ __('Scan now') }}
                    <svg class="size-3.5 transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </span>
            </a>
        </div>
    </section>

    {{-- Featured frames --}}
    @if ($featuredFrames->isNotEmpty())
        <section class="mt-20">
            <div class="mb-8 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="eyebrow">{{ __('Just in') }}</p>
                    <h2 class="mt-1 font-display text-2xl font-semibold text-ink sm:text-3xl">{{ __('Featured frames') }}</h2>
                </div>
                <a href="{{ route('frames.index') }}" class="text-sm font-medium text-accent hover:underline">{{ __('View all eyeglasses →') }}</a>
            </div>

            <div class="grid grid-cols-2 gap-x-6 gap-y-10 sm:grid-cols-4">
                @foreach ($featuredFrames as $frame)
                    <x-frame-card :frame="$frame" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Reviews / testimonials --}}
    <section class="hairline-top mt-24 pt-16">
        <div class="mb-10 text-center">
            <p class="eyebrow justify-center">{{ __('Reviews') }}</p>
            <h2 class="mt-1 font-display text-2xl font-semibold text-ink sm:text-3xl">{{ __('Trusted by people who actually wear them') }}</h2>
        </div>

        @if ($testimonials->isEmpty())
            <p class="text-center text-sm text-ink-faint">{{ __('No reviews yet — be the first to shop and share one.') }}</p>
        @else
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($testimonials as $review)
                    <article class="tick-frame reveal relative flex flex-col border border-hairline bg-white p-6 transition-all duration-200 hover:-translate-y-1 hover:border-accent hover:shadow-[5px_5px_0_var(--color-accent-soft)]">
                        <x-rating :value="$review->rating" />
                        @if ($review->title)
                            <p class="mt-3 font-display text-base font-semibold text-ink">{{ $review->title }}</p>
                        @endif
                        <p class="mt-2 flex-1 text-sm leading-relaxed text-ink-soft">&ldquo;{{ Str::limit($review->body, 180) }}&rdquo;</p>
                        <div class="hairline-top mt-4 flex items-center justify-between pt-4">
                            <div>
                                <p class="text-sm font-medium text-ink">{{ $review->user->first_name ?? __('Customer') }}</p>
                                @if ($review->reviewable)
                                    <p class="font-mono text-[11px] text-ink-faint">{{ $review->reviewable->name }}</p>
                                @endif
                            </div>
                            @if ($review->is_verified_purchase)
                                <span class="badge-accent">{{ __('Verified') }}</span>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Closing CTA --}}
    <section class="tick-frame-accent reveal relative mt-24 overflow-hidden border border-hairline bg-ink px-8 py-14 text-center text-white sm:px-16">
        <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
            <div class="absolute -left-12 -top-16 size-72 animate-float-slow rounded-full bg-accent opacity-60 blur-3xl"></div>
            <div class="absolute -right-10 -bottom-16 size-64 animate-float-slow rounded-full bg-pop opacity-50 blur-3xl" style="animation-delay: -3s;"></div>
        </div>
        <p class="font-mono text-[11px] uppercase tracking-[0.12em] text-white/60">{{ __('Ready when you are') }}</p>
        <h2 class="mx-auto mt-3 max-w-xl font-display text-3xl font-semibold tracking-tight sm:text-4xl">{{ __('Find your frame in the next five minutes.') }}</h2>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
            <a href="{{ route('frames.index') }}" class="btn-accent">{{ __('Shop now') }}</a>
            <a href="{{ route('face-match.create') }}" class="btn-outline-invert">{{ __('Scan my face shape') }}</a>
        </div>
    </section>
</x-layout>
