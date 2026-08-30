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
        <p class="mt-3 text-sm leading-relaxed text-ink-soft">{{ __('Snap a photo or pick one you already have. We\'ll measure your face shape, face width and pupillary distance, then match you to frames proportioned to fit.') }}</p>
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

                <div role="tablist" class="hairline-bottom flex gap-1 pb-px">
                    <button type="button" role="tab" aria-selected="true" data-face-scan-tab="camera" class="scan-tab">
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M4 8h3l1.5-2h7L17 8h3v11H4z" stroke-linejoin="round" />
                            <circle cx="12" cy="13" r="3.4" />
                        </svg>
                        {{ __('Take a photo') }}
                    </button>
                    <button type="button" role="tab" aria-selected="false" data-face-scan-tab="upload" class="scan-tab">
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M12 16V4m0 0L8 8m4-4 4 4M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        {{ __('Upload a photo') }}
                    </button>
                </div>

                <div data-face-scan-panel="camera" class="space-y-3">
                    <div class="relative overflow-hidden rounded-[3px] border border-hairline bg-ink/5">
                        {{-- Mirrored for a natural selfie view. This is a CSS transform
                             only — the underlying frames stay unflipped, so the left/right
                             monocular PD values aren't swapped. --}}
                        <video data-face-scan-video autoplay playsinline muted class="block aspect-[4/3] w-full -scale-x-100 object-cover"></video>
                        <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                            <div class="h-[78%] w-[52%] rounded-[50%] border-2 border-dashed border-white/60"></div>
                        </div>
                    </div>
                    <p class="text-xs text-ink-faint">{{ __('Fill the oval with your face, look straight at the camera, and hold still while it measures.') }}</p>
                    <button type="button" data-face-scan-capture disabled class="btn-accent w-full disabled:cursor-not-allowed disabled:opacity-50">
                        {{ __('Capture & scan') }}
                    </button>
                </div>

                <div data-face-scan-panel="upload" hidden class="space-y-3">
                    <div>
                        <label class="field-label" for="photo">{{ __('Your photo') }}</label>
                        <input type="file" id="photo" name="photo" accept="image/*" class="input !py-1.5 file:mr-3 file:rounded-[3px] file:border-0 file:bg-accent file:px-3 file:py-1.5 file:text-xs file:text-white">
                        <p class="mt-1.5 text-xs text-ink-faint">{{ __('Face the camera directly, in even light, with your whole face in frame. JPG or PNG.') }}</p>
                    </div>

                    <img data-face-scan-preview hidden alt="" class="mx-auto max-h-56 rounded-[3px] border border-hairline object-contain">

                    <button type="submit" data-face-scan-submit disabled class="btn-accent w-full disabled:cursor-not-allowed disabled:opacity-50">
                        {{ __('Scan my photo') }}
                    </button>
                </div>

                <p data-face-scan-status hidden
                   class="rounded-[3px] px-3 py-2 text-xs data-[tone=error]:bg-danger-soft data-[tone=error]:text-danger data-[tone=info]:bg-wash data-[tone=info]:text-ink-soft"></p>

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
