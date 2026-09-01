@php
    // The floating chat button already carries the shop's WhatsApp number, so
    // the contact column reads it from the same place rather than keeping a
    // second copy that could drift (see config/whatsapp.php).
    $whatsapp = preg_replace('/\D/', '', (string) config('whatsapp.number'));
    $phone = trim((string) config('contact.phone'));
    $email = trim((string) config('contact.email'));
    $supportEmail = trim((string) config('contact.support_email'));
    $mapUrl = trim((string) config('contact.map_url'));
    $addressLines = array_filter([
        config('contact.address'),
        trim(config('contact.city').', '.config('contact.country'), ', '),
    ]);
@endphp

<x-layout :title="__('About us')">
    {{-- Hero --}}
    <section class="relative -mx-6 overflow-hidden border-b border-hairline px-6 pb-16 pt-4 sm:pb-20">
        <div
            class="pointer-events-none absolute inset-0 -z-10"
            style="background-image: linear-gradient(var(--color-hairline) 1px, transparent 1px), linear-gradient(90deg, var(--color-hairline) 1px, transparent 1px); background-size: 44px 44px; mask-image: linear-gradient(to bottom, black, transparent 85%);"
        ></div>
        <div class="pointer-events-none absolute -right-28 -top-20 -z-10 size-[380px] animate-float-slow bg-accent/20 sm:size-[460px]" style="clip-path: polygon(20% 0, 100% 0, 100% 80%, 80% 100%, 0 100%, 0 20%);"></div>
        <div class="pointer-events-none absolute -bottom-28 left-1/4 -z-10 size-56 animate-float-slow bg-pop/20" style="clip-path: polygon(50% 0, 100% 50%, 50% 100%, 0 50%); animation-delay: -3s;"></div>

        <div class="mx-auto grid max-w-7xl grid-cols-1 items-center gap-12 pt-10 lg:grid-cols-[1.1fr_0.9fr]">
            <div>
                <p class="badge-accent">
                    <span class="size-1.5 animate-pulse-dot rounded-full bg-accent"></span>
                    {{ __('About us') }}
                </p>
                <h1 class="mt-5 max-w-xl font-display text-4xl font-semibold leading-[1.05] tracking-tight text-ink sm:text-5xl">
                    {{ __('A lens bench,') }}
                    <span class="relative inline-block whitespace-nowrap text-accent">
                        {{ __('not a showroom.') }}
                        <svg class="absolute -bottom-1 left-0 w-full text-pop" viewBox="0 0 200 10" preserveAspectRatio="none" fill="none">
                            <path d="M1 7.5C40 2.5 160 2.5 199 7.5" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                        </svg>
                    </span>
                </h1>
                <p class="mt-6 max-w-md text-base leading-relaxed text-ink-soft">
                    {{ __('Lucent Optics is an independent optician. We cut lenses to your prescription, fit frames to your actual face shape, and tell you plainly when something will not work — which is not something every shop will do.') }}
                </p>

                <div class="mt-8 flex flex-wrap items-center gap-4">
                    <a href="#contact" class="btn-accent">
                        {{ __('Contact us') }}
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </a>
                    <a href="{{ route('frames.index') }}" class="btn-outline-accent">{{ __('Browse the catalogue') }}</a>
                </div>

                <div class="mt-12 flex flex-wrap gap-x-10 gap-y-4 font-mono text-[11px] uppercase tracking-[0.08em] text-ink-faint">
                    <div>
                        <span class="block font-display text-2xl font-semibold tracking-tight text-ink">{{ $frameCount }}+</span>
                        {{ __('Frames on the shelf') }}
                    </div>
                    <div>
                        <span class="block font-display text-2xl font-semibold tracking-tight text-ink">{{ $reviewCount > 0 ? $reviewCount.'+' : '30-day' }}</span>
                        {{ $reviewCount > 0 ? __('Reviews published') : __('Free returns') }}
                    </div>
                    <div>
                        <span class="block font-display text-2xl font-semibold tracking-tight text-accent">1 day</span>
                        {{ __('Typical reply time') }}
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
                <span class="badge-pop absolute -bottom-3 left-6 bg-white">{{ __('Independent') }}</span>
                <span class="badge-accent absolute -top-3 right-6 bg-white">{{ __('Since 2019') }}</span>
            </div>
        </div>
    </section>

    {{-- Story --}}
    <section class="mt-20">
        <div class="grid grid-cols-1 gap-12 lg:grid-cols-[0.9fr_1.1fr]">
            <div>
                <p class="eyebrow">{{ __('Our story') }}</p>
                <h2 class="mt-2 font-display text-2xl font-semibold text-ink sm:text-3xl">{{ __('We got tired of guessing.') }}</h2>
            </div>
            <div class="space-y-5 text-sm leading-relaxed text-ink-soft">
                <p>{{ __('Lucent started in a single room in Beirut with one optician, one lens edger, and a stack of frames nobody in the neighbourhood could try on properly. Buying glasses meant holding them up to a mirror for four seconds and hoping. That is a strange way to choose something you will wear every day for two years.') }}</p>
                <p>{{ __('So we built the shop we wanted to walk into. Every frame is measured and listed with its real lens width, bridge and temple length. Every lens is cut in-house to the prescription you upload, and checked by a human before it is fitted. And because the hardest part is still knowing what suits you, we trained a face-shape match that reads a photo and narrows hundreds of frames down to the handful that will actually sit right.') }}</p>
                <p>{{ __('We are still small. That is the point: you get an answer from someone who has held the frame you are asking about.') }}</p>
            </div>
        </div>
    </section>

    {{-- What we stand for --}}
    <section class="mt-20">
        <div class="mb-8">
            <p class="eyebrow">{{ __('What we stand for') }}</p>
            <h2 class="mt-1 font-display text-2xl font-semibold text-ink sm:text-3xl">{{ __('Four things we will not compromise on') }}</h2>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['title' => __('Honest measurements'), 'body' => __('Real lens width, bridge and temple length on every listing — not "medium".'), 'icon' => 'M4 12h16M4 12l3-3M4 12l3 3M20 12l-3-3M20 12l-3 3'],
                ['title' => __('Lenses cut in-house'), 'body' => __('Your prescription is checked by an optician, then edged and fitted here.'), 'icon' => 'M12 3.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17Zm0 5.2a3.3 3.3 0 1 0 0 6.6 3.3 3.3 0 0 0 0-6.6Z'],
                ['title' => __('Plain answers'), 'body' => __('If a frame is wrong for your face or your Rx, we will say so before you pay.'), 'icon' => 'M5 5h14v10H9l-4 4V5Z'],
                ['title' => __('30-day returns'), 'body' => __('Wear them, walk around in them. Not right? Send them back, no interrogation.'), 'icon' => 'M4 12a8 8 0 1 1 2.5 5.8M4 12V7M4 12h5'],
            ] as $value)
                <article class="tick-frame reveal relative border border-hairline bg-white p-6 transition-all duration-200 hover:-translate-y-1 hover:border-accent hover:shadow-[5px_5px_0_var(--color-accent-soft)]">
                    <span class="flex size-9 items-center justify-center rounded-[3px] bg-accent-soft text-accent">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="{{ $value['icon'] }}" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <h3 class="mt-4 font-display text-base font-semibold text-ink">{{ $value['title'] }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-ink-soft">{{ $value['body'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    {{-- How an order comes together --}}
    <section class="mt-20">
        <div class="mb-8">
            <p class="eyebrow">{{ __('How it works') }}</p>
            <h2 class="mt-1 font-display text-2xl font-semibold text-ink sm:text-3xl">{{ __('From photo to fitted, in four steps') }}</h2>
        </div>

        <ol class="grid grid-cols-1 gap-px overflow-hidden border border-hairline bg-hairline sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['step' => '01', 'title' => __('Find your shape'), 'body' => __('Upload a photo to the AI face match, or filter the catalogue yourself.')],
                ['step' => '02', 'title' => __('Add your prescription'), 'body' => __('Type in the numbers or upload the scan. An optician verifies it.')],
                ['step' => '03', 'title' => __('We cut the lenses'), 'body' => __('Coatings, thinning and tint are applied, then edged to your frame.')],
                ['step' => '04', 'title' => __('It arrives'), 'body' => __('Cash on delivery, tracked in your inbox from the moment it ships.')],
            ] as $step)
                <li class="reveal bg-white p-6">
                    <span class="font-mono text-xs font-medium text-pop">{{ $step['step'] }}</span>
                    <h3 class="mt-2 font-display text-base font-semibold text-ink">{{ $step['title'] }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-ink-soft">{{ $step['body'] }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Contact --}}
    <section id="contact" class="mt-24 scroll-mt-28">
        <div class="mb-8">
            <p class="eyebrow">{{ __('Contact us') }}</p>
            <h2 class="mt-1 font-display text-2xl font-semibold text-ink sm:text-3xl">{{ __('Ask us anything') }}</h2>
            <p class="mt-2 max-w-lg text-sm leading-relaxed text-ink-soft">
                {{ __('Questions about a frame, a prescription, an order on its way, or a return you would like to start — write to us here and a person will answer, usually within one business day.') }}
            </p>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1.25fr_0.75fr]">
            {{-- The form itself --}}
            <div class="panel p-6 sm:p-8">
                {{-- The receipt lives here rather than in the layout's flash
                     banner: a sender redirected to #contact is looking at this
                     panel, not at the top of the page. --}}
                @if (session('contact_status'))
                    <div class="mb-6 flex items-start gap-3 rounded-[3px] border border-signal/30 bg-signal-soft px-4 py-3" role="status">
                        <svg class="mt-0.5 size-4 shrink-0 text-signal" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6">
                            <circle cx="10" cy="10" r="7.5" />
                            <path d="M6.8 10.2l2.1 2.1 4.3-4.6" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <p class="text-sm text-ink">{{ session('contact_status') }}</p>
                    </div>
                @endif

                <form method="POST" action="{{ route('contact.store') }}" class="space-y-5">
                    @csrf

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label class="field-label" for="contact-name">{{ __('Your name') }}</label>
                            <input
                                id="contact-name"
                                type="text"
                                name="name"
                                class="input"
                                value="{{ old('name', auth()->check() ? trim(auth()->user()->first_name.' '.auth()->user()->last_name) : '') }}"
                                required
                                maxlength="120"
                                autocomplete="name"
                            >
                            @error('name') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="field-label" for="contact-email">{{ __('Email') }}</label>
                            <input
                                id="contact-email"
                                type="email"
                                name="email"
                                class="input"
                                value="{{ old('email', auth()->user()?->email) }}"
                                required
                                maxlength="255"
                                autocomplete="email"
                            >
                            @error('email') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="field-label" for="contact-phone">{{ __('Phone (optional)') }}</label>
                            <input
                                id="contact-phone"
                                type="tel"
                                name="phone"
                                class="input"
                                value="{{ old('phone') }}"
                                maxlength="40"
                                autocomplete="tel"
                                placeholder="+961 …"
                            >
                            @error('phone') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="field-label" for="contact-topic">{{ __('What is it about?') }}</label>
                            <select id="contact-topic" name="topic" class="select" required>
                                @foreach (\App\Models\ContactMessage::TOPICS as $key => $label)
                                    <option value="{{ $key }}" @selected(old('topic') === $key)>{{ __($label) }}</option>
                                @endforeach
                            </select>
                            @error('topic') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="field-label" for="contact-message">{{ __('Your message') }}</label>
                        <textarea
                            id="contact-message"
                            name="message"
                            rows="6"
                            class="textarea"
                            required
                            minlength="10"
                            maxlength="4000"
                            placeholder="{{ __('Tell us what you need — an order number helps if it is about a purchase.') }}"
                        >{{ old('message') }}</textarea>
                        @error('message') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
                    </div>

                    {{-- Honeypot. Hidden from people and skipped by the keyboard;
                         a bot that fills every input gives itself away here. --}}
                    <div class="hidden" aria-hidden="true">
                        <label for="contact-website">{{ __('Leave this empty') }}</label>
                        <input id="contact-website" type="text" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="hairline-top flex flex-wrap items-center justify-between gap-4 pt-5">
                        <p class="max-w-xs font-mono text-[10.5px] leading-relaxed text-ink-faint">
                            {{ __('We use your details to answer you and nothing else.') }}
                        </p>
                        <button type="submit" class="btn-accent">
                            {{ __('Send message') }}
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M4 12l16-8-6 16-2.5-6.5L4 12Z" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>
                </form>
            </div>

            {{-- Direct channels --}}
            <div class="space-y-4">
                <div class="panel p-6">
                    <p class="eyebrow">{{ __('Reach us directly') }}</p>

                    <ul class="mt-4 space-y-4 text-sm">
                        @if ($phone !== '')
                            <li class="flex items-start gap-3">
                                <svg class="mt-0.5 size-4 shrink-0 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M5 4h3.2l1.6 4-2 1.4a12 12 0 0 0 5.8 5.8l1.4-2 4 1.6V19a1.6 1.6 0 0 1-1.8 1.6A15.6 15.6 0 0 1 3.4 5.8 1.6 1.6 0 0 1 5 4Z" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div>
                                    <p class="eyebrow">{{ __('Phone') }}</p>
                                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}" class="text-ink hover:text-accent">{{ $phone }}</a>
                                </div>
                            </li>
                        @endif

                        @if ($whatsapp !== '')
                            <li class="flex items-start gap-3">
                                <svg class="mt-0.5 size-4 shrink-0 text-[#25D366]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M12.05 2C6.5 2 2 6.5 2 12.05c0 1.77.46 3.5 1.34 5.02L2 22l5.06-1.32a10 10 0 0 0 4.99 1.32h.01c5.55 0 10.05-4.5 10.05-10.05C22.1 6.5 17.6 2 12.05 2Zm0 18.32h-.01a8.3 8.3 0 0 1-4.24-1.16l-.3-.18-3.15.82.84-3.07-.2-.32a8.3 8.3 0 1 1 7.06 3.91Z" />
                                </svg>
                                <div>
                                    <p class="eyebrow">{{ __('WhatsApp') }}</p>
                                    <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener noreferrer" class="text-ink hover:text-accent">{{ __('Message us on WhatsApp') }}</a>
                                    <p class="mt-0.5 font-mono text-[10.5px] text-ink-faint">{{ config('whatsapp.hours') }}</p>
                                </div>
                            </li>
                        @endif

                        @if ($email !== '')
                            <li class="flex items-start gap-3">
                                <svg class="mt-0.5 size-4 shrink-0 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <rect x="3" y="5" width="18" height="14" rx="1.5" />
                                    <path d="m3.6 6.2 8.4 6 8.4-6" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div>
                                    <p class="eyebrow">{{ __('Email') }}</p>
                                    <a href="mailto:{{ $email }}" class="break-all text-ink hover:text-accent">{{ $email }}</a>
                                    @if ($supportEmail !== '' && $supportEmail !== $email)
                                        <a href="mailto:{{ $supportEmail }}" class="mt-0.5 block break-all text-ink-soft hover:text-accent">{{ $supportEmail }}</a>
                                    @endif
                                </div>
                            </li>
                        @endif

                        @if (! empty($addressLines))
                            <li class="flex items-start gap-3">
                                <svg class="mt-0.5 size-4 shrink-0 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11Z" stroke-linejoin="round" />
                                    <circle cx="12" cy="10" r="2.6" />
                                </svg>
                                <div>
                                    <p class="eyebrow">{{ __('Studio') }}</p>
                                    @if ($mapUrl !== '')
                                        <a href="{{ $mapUrl }}" target="_blank" rel="noopener noreferrer" class="text-ink hover:text-accent">
                                            @foreach ($addressLines as $line)
                                                <span class="block">{{ $line }}</span>
                                            @endforeach
                                        </a>
                                    @else
                                        @foreach ($addressLines as $line)
                                            <span class="block text-ink">{{ $line }}</span>
                                        @endforeach
                                    @endif
                                </div>
                            </li>
                        @endif

                        @if (filled(config('contact.hours')))
                            <li class="flex items-start gap-3">
                                <svg class="mt-0.5 size-4 shrink-0 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <circle cx="12" cy="12" r="8.5" />
                                    <path d="M12 7.5V12l3 1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div>
                                    <p class="eyebrow">{{ __('Opening hours') }}</p>
                                    <p class="text-ink">{{ config('contact.hours') }}</p>
                                </div>
                            </li>
                        @endif
                    </ul>
                </div>

                <div class="panel p-6">
                    <p class="eyebrow">{{ __('Follow us') }}</p>
                    <p class="mt-2 text-sm leading-relaxed text-ink-soft">{{ __('New arrivals, fitting tips and the occasional look behind the bench.') }}</p>
                    <x-social-links class="mt-4" />
                </div>

                <div class="tick-frame relative border border-hairline bg-wash p-6">
                    <p class="eyebrow">{{ __('Already ordered?') }}</p>
                    <p class="mt-2 text-sm leading-relaxed text-ink-soft">
                        {{ __('Order updates, prescription checks and return decisions all land in your inbox on this site — often before we get a chance to write.') }}
                    </p>
                    <a href="{{ auth()->check() ? route('orders.index') : route('login') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-accent hover:underline">
                        {{ auth()->check() ? __('View my orders') : __('Sign in to track an order') }}
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- Closing CTA --}}
    <section class="tick-frame-accent reveal relative mt-24 overflow-hidden border border-hairline bg-ink px-8 py-14 text-center text-white sm:px-16">
        <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
            <div class="absolute -left-12 -top-16 size-72 animate-float-slow rounded-full bg-accent opacity-60 blur-3xl"></div>
            <div class="absolute -right-10 -bottom-16 size-64 animate-float-slow rounded-full bg-pop opacity-50 blur-3xl" style="animation-delay: -3s;"></div>
        </div>
        <p class="font-mono text-[11px] uppercase tracking-[0.12em] text-white/60">{{ __('Come and see') }}</p>
        <h2 class="mx-auto mt-3 max-w-xl font-display text-3xl font-semibold tracking-tight sm:text-4xl">{{ __('Your next pair is already on the shelf.') }}</h2>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
            <a href="{{ route('frames.index') }}" class="btn-accent">{{ __('Shop frames') }}</a>
            <a href="{{ route('face-match.create') }}" class="btn-outline-invert">{{ __('Try AI Face Match') }}</a>
        </div>
    </section>
</x-layout>
