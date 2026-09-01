@props(['title' => null])

@php
    // Employees browse the storefront to review the catalogue, but they don't shop.
    $isShopStaff = auth()->check() && auth()->user()->canAccessAdminConsole();
    $isDelivery = auth()->check() && auth()->user()->isDelivery();
    $isEmployee = $isShopStaff || $isDelivery;
    $cartCount = 0;

    if (! $isEmployee) {
        $cart = app(\App\Services\CartService::class)->current(request());
        $cartCount = $cart->eyeglasses()->sum('quantity') + $cart->contactLenses()->sum('quantity');
    }

    // Counted here rather than through a view composer, to keep the layout's
    // per-request lookups in one place alongside the cart count above.
    $unreadNotifications = auth()->check() ? auth()->user()->unreadNotifications()->count() : 0;
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' — Lucent Optics' : 'Lucent Optics' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-paper text-ink">

    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-[3px] focus:bg-ink focus:px-4 focus:py-2 focus:text-sm focus:text-white">
        {{ __('Skip to content') }}
    </a>

    <header class="hairline-bottom sticky top-0 z-40 bg-paper/95 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-6 py-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                <span class="tick-frame flex size-8 items-center justify-center">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" class="size-4">
                        <circle cx="8" cy="12" r="5.2" />
                        <circle cx="16" cy="12" r="5.2" />
                        <path d="M13.2 12h-2.4" />
                    </svg>
                </span>
                <span class="font-display text-[15px] font-semibold tracking-tight">Lucent Optics</span>
            </a>

            <nav class="hidden items-center gap-7 md:flex" aria-label="Primary">
                <a href="{{ route('frames.index') }}" class="nav-link {{ request()->routeIs('frames.*') ? 'is-active' : '' }}">{{ __('Eyeglasses') }}</a>
                <a href="{{ route('contact-lenses.index') }}" class="nav-link {{ request()->routeIs('contact-lenses.*') ? 'is-active' : '' }}">{{ __('Contact Lenses') }}</a>
                <a href="{{ route('collections.index') }}" class="nav-link {{ request()->routeIs('collections.*') ? 'is-active' : '' }}">{{ __('Collections') }}</a>
                <a href="{{ route('face-match.create') }}" class="nav-link {{ request()->routeIs('face-match.*') ? 'is-active' : '' }}">
                    {{ __('AI Face Match') }}
                    <span class="ml-1 inline-flex items-center gap-1 align-middle font-mono text-[9px] uppercase tracking-wider text-signal">
                        <span class="size-1.5 animate-pulse-dot rounded-full bg-signal"></span>
                        {{ __('beta') }}
                    </span>
                </a>
                <a href="{{ route('about') }}" class="nav-link {{ request()->routeIs('about') ? 'is-active' : '' }}">{{ __('About') }}</a>
                @auth
                    @unless ($isEmployee)
                        <a href="{{ route('orders.index') }}" class="nav-link {{ request()->routeIs('orders.*') ? 'is-active' : '' }}">{{ __('Orders') }}</a>
                        <a href="{{ route('prescriptions.index') }}" class="nav-link {{ request()->routeIs('prescriptions.*') ? 'is-active' : '' }}">{{ __('Prescriptions') }}</a>
                    @endunless
                @endauth
            </nav>

            <div class="flex items-center gap-4">
                @auth
                    <a href="{{ route('notifications.index') }}" class="nav-link relative flex items-center gap-1.5 {{ request()->routeIs('notifications.*') ? 'is-active' : '' }}" aria-label="{{ __('Inbox') }}">
                        <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4">
                            <path d="M18 8.5a6 6 0 1 0-12 0c0 5-2 6.5-2 6.5h16s-2-1.5-2-6.5" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M13.7 19a2 2 0 0 1-3.4 0" stroke-linecap="round" />
                        </svg>
                        @if ($unreadNotifications > 0)
                            <span class="flex size-4 items-center justify-center rounded-full bg-signal font-mono text-[10px] text-white">{{ $unreadNotifications > 9 ? '9+' : $unreadNotifications }}</span>
                        @endif
                    </a>
                @endauth

                @unless ($isEmployee)
                <a href="{{ route('cart.index') }}" class="nav-link relative flex items-center gap-1.5 {{ request()->routeIs('cart.*') ? 'is-active' : '' }}" aria-label="{{ __('Cart') }}">
                    <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4">
                        <path d="M4 6h2l1.6 10.2a2 2 0 0 0 2 1.8h7.4a2 2 0 0 0 2-1.7L20 9H7" stroke-linecap="round" stroke-linejoin="round" />
                        <circle cx="10" cy="20" r="1.15" fill="currentColor" stroke="none" />
                        <circle cx="17" cy="20" r="1.15" fill="currentColor" stroke="none" />
                    </svg>
                    @if ($cartCount > 0)
                        <span class="flex size-4 items-center justify-center rounded-full bg-accent font-mono text-[10px] text-white">{{ $cartCount }}</span>
                    @endif
                </a>
                @else
                    <span class="badge-neutral font-mono text-[10px] uppercase tracking-wider">{{ $isDelivery ? __('Delivery view') : __('Staff view') }}</span>
                @endunless

                @auth
                    <div class="hidden items-center gap-4 md:flex">
                        @if ($isShopStaff)
                            <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.*') ? 'is-active' : '' }}">{{ __('Admin') }}</a>
                        @elseif ($isDelivery)
                            <a href="{{ route('delivery.orders.index') }}" class="nav-link {{ request()->routeIs('delivery.*') ? 'is-active' : '' }}">{{ __('Delivery') }}</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn-ghost btn-sm !px-0">{{ __('Sign out') }}</button>
                        </form>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="btn-outline-accent btn-sm hidden md:inline-flex">{{ __('Sign in') }}</a>
                @endauth
            </div>
        </div>

        <nav class="hairline-top flex items-center gap-5 overflow-x-auto px-6 py-2.5 md:hidden" aria-label="Primary">
            <a href="{{ route('frames.index') }}" class="nav-link whitespace-nowrap {{ request()->routeIs('frames.*') ? 'is-active' : '' }}">{{ __('Eyeglasses') }}</a>
            <a href="{{ route('contact-lenses.index') }}" class="nav-link whitespace-nowrap {{ request()->routeIs('contact-lenses.*') ? 'is-active' : '' }}">{{ __('Contacts') }}</a>
            <a href="{{ route('face-match.create') }}" class="nav-link whitespace-nowrap {{ request()->routeIs('face-match.*') ? 'is-active' : '' }}">{{ __('AI Match') }}</a>
            <a href="{{ route('about') }}" class="nav-link whitespace-nowrap {{ request()->routeIs('about') ? 'is-active' : '' }}">{{ __('About') }}</a>
            @auth
                <a href="{{ route('notifications.index') }}" class="nav-link whitespace-nowrap {{ request()->routeIs('notifications.*') ? 'is-active' : '' }}">
                    {{ __('Inbox') }}@if ($unreadNotifications > 0) ({{ $unreadNotifications }})@endif
                </a>
                @if ($isShopStaff)
                    <a href="{{ route('admin.dashboard') }}" class="nav-link whitespace-nowrap {{ request()->routeIs('admin.*') ? 'is-active' : '' }}">{{ __('Admin') }}</a>
                @elseif ($isDelivery)
                    <a href="{{ route('delivery.orders.index') }}" class="nav-link whitespace-nowrap {{ request()->routeIs('delivery.*') ? 'is-active' : '' }}">{{ __('Delivery') }}</a>
                @else
                    <a href="{{ route('orders.index') }}" class="nav-link whitespace-nowrap {{ request()->routeIs('orders.*') ? 'is-active' : '' }}">{{ __('Orders') }}</a>
                    <a href="{{ route('prescriptions.index') }}" class="nav-link whitespace-nowrap {{ request()->routeIs('prescriptions.*') ? 'is-active' : '' }}">{{ __('Prescriptions') }}</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nav-link whitespace-nowrap">{{ __('Sign out') }}</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="nav-link whitespace-nowrap {{ request()->routeIs('login') ? 'is-active' : '' }}">{{ __('Sign in') }}</a>
            @endauth
        </nav>
    </header>

    <main id="main" class="mx-auto max-w-7xl px-6 py-10">
        <x-flash />

        {{--
            Nothing is gated on a confirmed address today, so this reminder is
            the whole enforcement: it follows an unverified account around the
            site until they click the link. Hidden on the verification pages
            themselves, which already say all of this.
        --}}
        @auth
            @if (! auth()->user()->hasVerifiedEmail() && ! request()->routeIs('verification.*'))
                <div class="panel border-warn/30 bg-warn-soft mb-6 flex items-start gap-3 px-4 py-3" role="status">
                    <svg class="mt-0.5 size-4 shrink-0 text-warn" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M3 5.5h14v9H3z" /><path d="M3 6l7 5 7-5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <p class="text-sm text-ink">
                        {{ __('Confirm your email address so we can send you order and delivery updates.') }}
                        <a href="{{ route('verification.notice') }}" class="text-accent underline hover:no-underline">{{ __('Resend the link') }}</a>
                    </p>
                </div>
            @endif
        @endauth

        {{ $slot }}
    </main>

    <footer class="hairline-top mt-24">
        <div class="mx-auto max-w-7xl px-6 py-10">
            <div class="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
                <div>
                    <div class="flex items-center gap-2.5">
                        <span class="tick-frame flex size-7 items-center justify-center">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" class="size-3.5">
                                <circle cx="8" cy="12" r="5.2" />
                                <circle cx="16" cy="12" r="5.2" />
                                <path d="M13.2 12h-2.4" />
                            </svg>
                        </span>
                        <span class="font-display text-sm font-semibold">Lucent Optics</span>
                    </div>
                    <p class="mt-2 max-w-xs text-sm text-ink-soft">{{ __('Precision eyewear, fitted with an AI face-shape match and lenses built to your prescription.') }}</p>

                    {{-- Details come from config/contact.php, the same source the
                         About page reads, so the two can never disagree. --}}
                    <p class="mt-3 space-x-3 font-mono text-[11px] text-ink-faint">
                        @if (filled(config('contact.phone')))
                            <a href="tel:{{ preg_replace('/[^\d+]/', '', (string) config('contact.phone')) }}" class="hover:text-ink">{{ config('contact.phone') }}</a>
                        @endif
                        @if (filled(config('contact.email')))
                            <a href="mailto:{{ config('contact.email') }}" class="hover:text-ink">{{ config('contact.email') }}</a>
                        @endif
                    </p>

                    <x-social-links size="sm" class="mt-4" />
                </div>
                <div class="flex flex-wrap gap-12 font-mono text-[11px] uppercase tracking-[0.08em] text-ink-soft">
                    <div class="space-y-2">
                        <p class="text-ink-faint">{{ __('Shop') }}</p>
                        <a href="{{ route('frames.index') }}" class="block hover:text-ink">{{ __('Eyeglasses') }}</a>
                        <a href="{{ route('contact-lenses.index') }}" class="block hover:text-ink">{{ __('Contact Lenses') }}</a>
                        <a href="{{ route('face-match.create') }}" class="block hover:text-ink">{{ __('AI Face Match') }}</a>
                    </div>
                    <div class="space-y-2">
                        <p class="text-ink-faint">{{ __('Company') }}</p>
                        <a href="{{ route('about') }}" class="block hover:text-ink">{{ __('About us') }}</a>
                        <a href="{{ route('about') }}#contact" class="block hover:text-ink">{{ __('Contact us') }}</a>
                        <a href="{{ route('collections.index') }}" class="block hover:text-ink">{{ __('Collections') }}</a>
                    </div>
                    <div class="space-y-2">
                        <p class="text-ink-faint">{{ __('Account') }}</p>
                        @auth
                            @if ($isShopStaff)
                                <a href="{{ route('admin.dashboard') }}" class="block hover:text-ink">{{ __('Staff console') }}</a>
                            @elseif ($isDelivery)
                                <a href="{{ route('delivery.orders.index') }}" class="block hover:text-ink">{{ __('Delivery console') }}</a>
                            @else
                                <a href="{{ route('orders.index') }}" class="block hover:text-ink">{{ __('Orders') }}</a>
                                <a href="{{ route('prescriptions.index') }}" class="block hover:text-ink">{{ __('Prescriptions') }}</a>
                            @endif
                            <a href="{{ route('notifications.index') }}" class="block hover:text-ink">{{ __('Inbox') }}</a>
                        @else
                            <a href="{{ route('login') }}" class="block hover:text-ink">{{ __('Sign in') }}</a>
                            <a href="{{ route('register') }}" class="block hover:text-ink">{{ __('Create account') }}</a>
                        @endauth
                    </div>
                </div>
            </div>
            <p class="hairline-top mt-8 pt-6 font-mono text-[10.5px] text-ink-faint">&copy; {{ date('Y') }} Lucent Optics.</p>
        </div>
    </footer>

    {{-- Customer support channel, so it follows the same staff/customer split as the cart. --}}
    @unless ($isEmployee)
        <x-whatsapp-chat />
    @endunless

    @stack('scripts')
</body>
</html>
