<x-admin-layout title="Dashboard" heading="Overview" :subheading="__('Last :days days', ['days' => $days])">

    <div class="mb-8 flex justify-end gap-2">
        @foreach ([7 => '7d', 30 => '30d', 90 => '90d'] as $value => $label)
            <a href="{{ route('admin.dashboard', ['days' => $value]) }}" class="btn-sm {{ $days === $value ? 'btn-primary' : 'btn-outline' }}">{{ $label }}</a>
        @endforeach
    </div>

    {{-- Attention queue --}}
    <div class="mb-10 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <a href="{{ route('admin.orders.index', ['status' => 'pending']) }}" class="panel flex items-center justify-between p-5 transition-colors hover:border-ink">
            <div>
                <p class="eyebrow">{{ __('Pending orders') }}</p>
                <p class="mt-1 font-display text-2xl font-semibold text-ink">{{ $pendingOrdersCount }}</p>
            </div>
            @if ($pendingOrdersCount > 0)<span class="size-2 rounded-full bg-warn"></span>@endif
        </a>
        <a href="{{ route('admin.returns.index') }}" class="panel flex items-center justify-between p-5 transition-colors hover:border-ink">
            <div>
                <p class="eyebrow">{{ __('Open returns') }}</p>
                <p class="mt-1 font-display text-2xl font-semibold text-ink">{{ $openReturnsCount }}</p>
            </div>
            @if ($openReturnsCount > 0)<span class="size-2 rounded-full bg-warn"></span>@endif
        </a>
        <a href="{{ route('admin.reviews.index') }}" class="panel flex items-center justify-between p-5 transition-colors hover:border-ink">
            <div>
                <p class="eyebrow">{{ __('Reviews to approve') }}</p>
                <p class="mt-1 font-display text-2xl font-semibold text-ink">{{ $pendingReviewsCount }}</p>
            </div>
            @if ($pendingReviewsCount > 0)<span class="size-2 rounded-full bg-warn"></span>@endif
        </a>
    </div>

    {{-- Revenue --}}
    <div class="panel mb-10 p-6">
        <div class="mb-6 flex items-baseline justify-between">
            <div>
                <p class="eyebrow">{{ __('Revenue') }}</p>
                <p class="mt-1 font-display text-3xl font-semibold text-ink">${{ number_format($totalRevenue, 2) }}</p>
            </div>
            <p class="font-mono text-xs text-ink-faint">{{ $totalOrders }} {{ Str::plural('order', $totalOrders) }}</p>
        </div>

        @if ($revenueByDay->isEmpty())
            <p class="text-sm text-ink-faint">{{ __('No orders in this window yet.') }}</p>
        @else
            @php $maxRevenue = max(1, $revenueByDay->max('revenue')); @endphp
            <div class="flex h-32 items-end gap-1">
                @foreach ($revenueByDay as $row)
                    <div class="group relative flex-1">
                        <div class="w-full rounded-t-[2px] bg-ink transition-colors group-hover:bg-signal" style="height: {{ max(2, ($row->revenue / $maxRevenue) * 128) }}px"></div>
                        <div class="pointer-events-none absolute bottom-full left-1/2 mb-2 -translate-x-1/2 whitespace-nowrap rounded-[3px] bg-ink px-2 py-1 font-mono text-[10px] text-white opacity-0 transition-opacity group-hover:opacity-100">
                            {{ \Illuminate\Support\Carbon::parse($row->day)->format('M j') }} &middot; ${{ number_format($row->revenue, 2) }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Best sellers --}}
        <div class="panel p-6">
            <p class="eyebrow mb-4">{{ __('Best sellers by units') }}</p>
            @if ($topFramesBySales->isEmpty())
                <p class="text-sm text-ink-faint">{{ __('No sales yet.') }}</p>
            @else
                <div class="space-y-3">
                    @foreach ($topFramesBySales as $row)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-ink">{{ $row->frame_name }}</span>
                            <span class="font-mono text-ink-soft">{{ $row->units_sold }} {{ __('units') }} &middot; ${{ number_format($row->revenue, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Most viewed --}}
        <div class="panel p-6">
            <p class="eyebrow mb-4">{{ __('Most viewed frames') }}</p>
            @if ($mostViewedFrames->isEmpty())
                <p class="text-sm text-ink-faint">{{ __('No views recorded yet.') }}</p>
            @else
                <div class="space-y-3">
                    @foreach ($mostViewedFrames as $row)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-ink">{{ $row['name'] }}</span>
                            <span class="font-mono text-ink-soft">{{ $row['views'] }} {{ __('views') }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Low stock --}}
        <div class="panel p-6">
            <p class="eyebrow mb-4">{{ __('Low stock — frames') }}</p>
            @if ($lowStockFrames->isEmpty())
                <p class="text-sm text-ink-faint">{{ __('All frames well stocked.') }}</p>
            @else
                <div class="space-y-3">
                    @foreach ($lowStockFrames as $frame)
                        <a href="{{ route('admin.frames.edit', $frame) }}" class="flex items-center justify-between text-sm hover:text-ink">
                            <span class="text-ink-soft hover:text-ink">{{ $frame->name }}</span>
                            <span class="badge-{{ $frame->stock === 0 ? 'danger' : 'warn' }}">{{ $frame->stock }} {{ __('left') }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="panel p-6">
            <p class="eyebrow mb-4">{{ __('Low stock — contact lenses') }}</p>
            @if ($lowStockContactLenses->isEmpty())
                <p class="text-sm text-ink-faint">{{ __('All contact lenses well stocked.') }}</p>
            @else
                <div class="space-y-3">
                    @foreach ($lowStockContactLenses as $lens)
                        <a href="{{ route('admin.contact-lenses.edit', $lens) }}" class="flex items-center justify-between text-sm hover:text-ink">
                            <span class="text-ink-soft hover:text-ink">{{ $lens->name }}</span>
                            <span class="badge-{{ $lens->stock === 0 ? 'danger' : 'warn' }}">{{ $lens->stock }} {{ __('left') }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
