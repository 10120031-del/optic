<x-layout title="AI Face Match">
    <div class="mx-auto max-w-2xl text-center">
        <p class="eyebrow inline-flex items-center justify-center gap-1.5">
            {{ __('AI Face Match') }}
            <span class="inline-flex items-center gap-1 text-signal">
                <span class="size-1.5 animate-pulse-dot rounded-full bg-signal"></span>
                {{ __('beta') }}
            </span>
        </p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-ink sm:text-4xl">{{ __('Find the frames built for your face') }}</h1>
        <p class="mt-3 text-sm leading-relaxed text-ink-soft">{{ __('Choose a straight-on photo and we\'ll measure your face shape, face width and pupillary distance, then match you to frames proportioned to fit.') }}</p>
        <p class="mt-3 inline-flex items-center gap-1.5 rounded-[3px] bg-wash px-3 py-1.5 text-xs text-ink-soft">
            <svg class="size-3.5 shrink-0 text-signal" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M12 3 4 6v6c0 4.4 3.4 8.4 8 9 4.6-.6 8-4.6 8-9V6l-8-3Z" />
            </svg>
            {{ __('Your photo is measured on your device and never uploaded.') }}
        </p>
    </div>

    <div class="mx-auto mt-10 max-w-lg">
        <div class="tick-frame panel p-8">
            <form method="POST" action="{{ route('face-match.analyze') }}" class="space-y-4" data-face-scan>
                @csrf
                <div>
                    <label class="field-label" for="photo">{{ __('Your photo') }}</label>
                    <input type="file" id="photo" name="photo" accept="image/*" required class="input !py-1.5 file:mr-3 file:rounded-[3px] file:border-0 file:bg-accent file:px-3 file:py-1.5 file:text-xs file:text-white">
                    <p class="mt-1.5 text-xs text-ink-faint">{{ __('Face the camera directly, in even light, with your whole face in frame. JPG or PNG.') }}</p>
                </div>

                <img data-face-scan-preview hidden alt="" class="mx-auto max-h-56 rounded-[3px] border border-hairline object-contain">

                <p data-face-scan-status hidden
                   class="rounded-[3px] px-3 py-2 text-xs data-[tone=error]:bg-danger-soft data-[tone=error]:text-danger data-[tone=info]:bg-wash data-[tone=info]:text-ink-soft"></p>

                <button type="submit" data-face-scan-submit disabled class="btn-accent w-full disabled:cursor-not-allowed disabled:opacity-50">
                    {{ __('Scan my photo') }}
                </button>

                <noscript>
                    <p class="text-xs text-ink-faint">{{ __('Scanning needs JavaScript. Pick your face shape below instead.') }}</p>
                </noscript>
            </form>
        </div>
    </div>

    <div class="hairline-top mx-auto mt-16 max-w-4xl pt-12">
        <p class="text-center text-sm text-ink-soft">{{ __('Or tell us your face shape directly') }}</p>
        <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
            @forelse ($faceShapes as $shape)
                <a href="{{ route('face-match.recommend', $shape) }}" class="panel group flex flex-col items-center gap-2 px-4 py-6 text-center transition-colors hover:border-accent">
                    <svg class="size-8 text-ink-faint transition-colors group-hover:text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3">
                        <ellipse cx="12" cy="12" rx="7" ry="9" />
                    </svg>
                    <span class="text-sm font-medium text-ink">{{ $shape->name }}</span>
                    @if ($shape->description)
                        <span class="text-xs text-ink-faint">{{ Str::limit($shape->description, 40) }}</span>
                    @endif
                </a>
            @empty
                <p class="col-span-full text-sm text-ink-faint">{{ __('Face shapes haven\'t been set up yet.') }}</p>
            @endforelse
        </div>
    </div>

    @push('scripts')
        @vite('resources/js/face-scan.js')
    @endpush
</x-layout>
