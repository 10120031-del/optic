@props(['title' => null, 'heading' => null])

@php
    $unreadNotifications = auth()->check() ? auth()->user()->unreadNotifications()->count() : 0;
    $user = auth()->user();

    $allNavItems = [
        ['label' => __('Inbox'), 'route' => 'notifications.index', 'pattern' => 'notifications.*', 'badge' => $unreadNotifications, 'roles' => ['owner', 'staff', 'delivery']],
        ['label' => __('Dashboard'), 'route' => 'admin.dashboard', 'pattern' => 'admin.dashboard', 'roles' => ['owner', 'staff']],
        ['label' => __('Frames'), 'route' => 'admin.frames.index', 'pattern' => 'admin.frames.*', 'roles' => ['owner', 'staff']],
        ['label' => __('Lens Packages'), 'route' => 'admin.lenses.index', 'pattern' => 'admin.lenses.*', 'roles' => ['owner', 'staff']],
        ['label' => __('Lens Features'), 'route' => 'admin.lens-features.index', 'pattern' => 'admin.lens-features.*', 'roles' => ['owner', 'staff']],
        ['label' => __('Contact Lenses'), 'route' => 'admin.contact-lenses.index', 'pattern' => 'admin.contact-lenses.*', 'roles' => ['owner', 'staff']],
        ['label' => __('Collections'), 'route' => 'admin.collections.index', 'pattern' => 'admin.collections.*', 'roles' => ['owner', 'staff']],
        ['label' => __('Orders'), 'route' => $user?->isDelivery() ? 'delivery.orders.index' : 'admin.orders.index', 'pattern' => $user?->isDelivery() ? 'delivery.orders.*' : 'admin.orders.*', 'badge' => $pendingOrdersCount ?? 0, 'roles' => ['owner', 'staff', 'delivery']],
        ['label' => __('Returns'), 'route' => 'admin.returns.index', 'pattern' => 'admin.returns.*', 'roles' => ['owner', 'staff']],
        ['label' => __('Prescriptions'), 'route' => 'admin.prescriptions.index', 'pattern' => 'admin.prescriptions.*', 'roles' => ['owner', 'staff']],
        ['label' => __('Reviews'), 'route' => 'admin.reviews.index', 'pattern' => 'admin.reviews.*', 'roles' => ['owner', 'staff']],
        ['label' => __('Promotions'), 'route' => 'admin.promotions.index', 'pattern' => 'admin.promotions.*', 'roles' => ['owner']],
    ];

    $role = match (true) {
        $user?->isOwner() => 'owner',
        $user?->isStaff() => 'staff',
        $user?->isDelivery() => 'delivery',
        default => null,
    };

    $navItems = collect($allNavItems)
        ->filter(fn ($item) => $role && in_array($role, $item['roles'], true))
        ->values()
        ->all();
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' — Admin — Lucent Optics' : 'Admin — Lucent Optics' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-paper text-ink">
    <div class="flex min-h-screen">
        <aside class="hairline-bottom md:hairline-bottom-0 md:border-r md:border-hairline flex w-full shrink-0 flex-col md:w-60">
            <div class="hairline-bottom flex items-center gap-2.5 px-6 py-5">
                <span class="tick-frame flex size-8 items-center justify-center">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" class="size-4">
                        <circle cx="8" cy="12" r="5.2" />
                        <circle cx="16" cy="12" r="5.2" />
                        <path d="M13.2 12h-2.4" />
                    </svg>
                </span>
                <div class="leading-tight">
                    <p class="font-display text-sm font-semibold">Lucent Optics</p>
                    <p class="eyebrow">{{ $user?->isDelivery() ? __('Delivery console') : __('Staff console') }}</p>
                </div>
            </div>

            <nav class="flex-1 space-y-0.5 overflow-x-auto px-3 py-4 md:overflow-visible" aria-label="Admin">
                @foreach ($navItems as $item)
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center gap-2.5 whitespace-nowrap rounded-[3px] px-3 py-2 text-sm transition-colors
                              {{ request()->routeIs($item['pattern']) ? 'bg-ink text-white' : 'text-ink-soft hover:bg-wash hover:text-ink' }}">
                        {{ $item['label'] }}
                        @if (($item['badge'] ?? 0) > 0)
                            <span class="ml-auto font-mono text-[10px] {{ request()->routeIs($item['pattern']) ? 'text-white/70' : 'text-ink-faint' }}">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>

            <div class="hairline-top space-y-2 px-6 py-5">
                <a href="{{ route('home') }}" class="nav-link block text-xs">&larr; {{ __('Back to store') }}</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-ghost btn-sm !px-0">{{ __('Sign out') }}</button>
                </form>
            </div>
        </aside>

        <div class="min-w-0 flex-1">
            <main class="mx-auto max-w-6xl px-6 py-10 md:px-10">
                @if ($heading)
                    <div class="mb-8">
                        <h1 class="font-display text-2xl font-semibold text-ink">{{ $heading }}</h1>
                        @isset($subheading)
                            <p class="mt-1 text-sm text-ink-soft">{{ $subheading }}</p>
                        @endisset
                    </div>
                @endif
                <x-flash />
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
