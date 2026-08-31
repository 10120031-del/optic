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

    @if (auth()->user()?->isOwner())
        <div class="mb-10">
            <a href="{{ route('admin.users.index') }}" class="panel flex flex-wrap items-center justify-between gap-4 p-5 transition-colors hover:border-ink">
                <div>
                    <p class="eyebrow">{{ __('Team accounts') }}</p>
                    <p class="mt-1 text-sm text-ink-soft">{{ __('Promote customers to staff or delivery, or move them back to a shopper account.') }}</p>
                </div>
                <div class="flex gap-6 font-mono text-xs text-ink-faint">
                    <span>{{ trans_choice(':count staff member|:count staff members', $staffCount, ['count' => $staffCount]) }}</span>
                    <span>{{ trans_choice(':count delivery|:count delivery', $deliveryCount, ['count' => $deliveryCount]) }}</span>
                </div>
            </a>
        </div>
    @endif

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

    {{-- ------------------------------------------------------------------
         What sold, what is on the shelf, and how the catalogue is doing.
         Every chart below is hand-rolled SVG/CSS on the same theme tokens —
         no charting library, nothing to load.
    ------------------------------------------------------------------- --}}
    <div class="mb-10 grid grid-cols-1 gap-6 lg:grid-cols-2">

        {{-- Frames bought, as a share of all frames sold --}}
        <div class="panel p-6">
            <div class="mb-5 flex items-baseline justify-between gap-3">
                <p class="eyebrow">{{ __('Frames bought') }}</p>
                <p class="font-mono text-xs text-ink-faint">
                    {{ $framesSoldShare['total'] }} {{ __('units') }} &middot; {{ $framesSoldShare['distinct'] }} {{ Str::plural('model', $framesSoldShare['distinct']) }}
                </p>
            </div>
            <x-donut-chart
                :segments="collect($framesSoldShare['segments'])->map(fn ($s) => $s + ['meta' => $s['value'].' '.__('units')])->all()"
                :center-value="$framesSoldShare['total']"
                :center-label="__('units')"
                :empty="__('No frames sold in this window.')" />
        </div>

        {{-- Revenue split across the two product lines --}}
        <div class="panel p-6">
            @php $mixTotal = collect($salesMix)->sum('value'); @endphp
            <div class="mb-5 flex items-baseline justify-between gap-3">
                <p class="eyebrow">{{ __('Sales mix') }}</p>
                <p class="font-mono text-xs text-ink-faint">${{ number_format($mixTotal, 2) }} {{ __('in product revenue') }}</p>
            </div>
            <x-donut-chart
                :segments="collect($salesMix)->map(fn ($s) => $s + ['meta' => '$'.number_format($s['value'], 2)])->all()"
                :center-value="collect($salesMix)->sum('units')"
                :center-label="__('items')"
                :empty="__('No product lines sold in this window.')" />
        </div>

        {{-- Available stock, by where it sits in the catalogue --}}
        <div class="panel p-6">
            <div class="mb-5 flex items-baseline justify-between gap-3">
                <p class="eyebrow">{{ __('Available stock') }}</p>
                <p class="font-mono text-xs text-ink-faint">{{ collect($stockByCategory)->sum('value') }} {{ __('units on hand') }}</p>
            </div>
            <x-bar-chart :rows="$stockByCategory" color="var(--color-signal)" :empty="__('No active products in the catalogue.')" />
        </div>

        {{-- How much of the catalogue is actually sellable right now --}}
        <div class="panel p-6">
            @php $healthTotal = collect($stockHealth['segments'])->sum('value'); @endphp
            <div class="mb-5 flex items-baseline justify-between gap-3">
                <p class="eyebrow">{{ __('Stock health') }}</p>
                <p class="font-mono text-xs text-ink-faint">{{ $stockHealth['units'] }} {{ __('units across the shelf') }}</p>
            </div>
            <x-donut-chart
                :segments="$stockHealth['segments']"
                :center-value="$healthTotal"
                :center-label="__('products')"
                :empty="__('No active products in the catalogue.')" />
        </div>

        {{-- Order pipeline --}}
        <div class="panel p-6">
            <div class="mb-5 flex items-baseline justify-between gap-3">
                <p class="eyebrow">{{ __('Orders by status') }}</p>
                <p class="font-mono text-xs text-ink-faint">{{ collect($ordersByStatus)->sum('value') }} {{ __('orders') }}</p>
            </div>
            <x-bar-chart
                :rows="collect($ordersByStatus)->map(fn ($row) => $row + [
                    'meta' => $row['value'],
                    'href' => route('admin.orders.index', ['status' => $row['status']]),
                    'color' => in_array($row['status'], ['cancelled', 'refunded'], true) ? 'var(--color-danger)' : 'var(--color-accent)',
                ])->all()"
                :empty="__('No orders in this window yet.')" />
        </div>

        {{-- Which days of the week the shop actually sells on --}}
        <div class="panel p-6">
            <div class="mb-5 flex items-baseline justify-between gap-3">
                <p class="eyebrow">{{ __('Busiest weekdays') }}</p>
                <p class="font-mono text-xs text-ink-faint">{{ __('orders placed') }}</p>
            </div>
            @php $maxWeekday = max(1, collect($ordersByWeekday)->max('value')); @endphp
            @if (collect($ordersByWeekday)->sum('value') === 0)
                <p class="text-sm text-ink-faint">{{ __('No orders in this window yet.') }}</p>
            @else
                {{-- Bars sized in px against a fixed-height track, like the
                     revenue chart above: a percentage height has nothing to
                     resolve against inside a flex column. --}}
                <div class="flex h-28 items-end gap-2">
                    @foreach ($ordersByWeekday as $day)
                        <div class="group relative flex-1">
                            <div class="w-full rounded-t-[2px] {{ $day['value'] > 0 ? 'bg-ink' : 'bg-hairline' }} transition-colors group-hover:bg-signal"
                                 style="height: {{ $day['value'] > 0 ? max(4, ($day['value'] / $maxWeekday) * 112) : 2 }}px"></div>
                            <div class="pointer-events-none absolute bottom-full left-1/2 mb-2 -translate-x-1/2 whitespace-nowrap rounded-[3px] bg-ink px-2 py-1 font-mono text-[10px] text-white opacity-0 transition-opacity group-hover:opacity-100">
                                {{ $day['value'] }} {{ Str::plural('order', $day['value']) }} &middot; ${{ number_format($day['revenue'], 2) }}
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 flex gap-2">
                    @foreach ($ordersByWeekday as $day)
                        <span class="flex-1 text-center font-mono text-[10px] uppercase tracking-[0.06em] text-ink-faint">{{ $day['label'] }}</span>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Which silhouettes move --}}
        <div class="panel p-6">
            <p class="eyebrow mb-5">{{ __('Units sold by frame shape') }}</p>
            <x-bar-chart :rows="$unitsByShape" color="var(--color-pop)" :empty="__('No frames sold in this window.')" />
        </div>

        {{-- Which brands pay the bills --}}
        <div class="panel p-6">
            <p class="eyebrow mb-5">{{ __('Top brands by revenue') }}</p>
            <x-bar-chart :rows="$topBrands" color="var(--color-accent)" :empty="__('No branded frames sold in this window.')" />
        </div>

        {{-- Standing reputation --}}
        <div class="panel p-6 lg:col-span-2">
            <div class="mb-5 flex items-baseline justify-between gap-3">
                <p class="eyebrow">{{ __('Review ratings') }}</p>
                <p class="font-mono text-xs text-ink-faint">
                    @if ($ratingDistribution['average'])
                        {{ $ratingDistribution['average'] }}/5 &middot; {{ $ratingDistribution['total'] }} {{ Str::plural('review', $ratingDistribution['total']) }}
                    @else
                        {{ __('no approved reviews yet') }}
                    @endif
                </p>
            </div>
            <x-bar-chart
                :rows="collect($ratingDistribution['rows'])->map(fn ($row) => [
                    'label' => str_repeat('★', $row['stars']).str_repeat('☆', 5 - $row['stars']),
                    'value' => $row['value'],
                    'meta' => $row['value'].' ('.($ratingDistribution['total'] > 0 ? round($row['value'] / $ratingDistribution['total'] * 100) : 0).'%)',
                ])->all()"
                color="var(--color-warn)"
                :empty="__('No approved reviews yet.')" />
        </div>
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
