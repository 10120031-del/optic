<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactLens;
use App\Models\Frame;
use App\Models\Order;
use App\Models\OrderContactLens;
use App\Models\OrderEyeglass;
use App\Models\OrderReturn;
use App\Models\ProductView;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Orders in these states never happened as far as the trend numbers are
     * concerned — counting them would inflate revenue and best-seller ranks.
     */
    private const NON_SALE_STATUSES = ['cancelled'];

    /**
     * Requirement 6's "track trends and statistics": revenue over time,
     * best sellers by units and by view-to-purchase interest, what is on the
     * shelf right now, how sales split across product lines, categories,
     * shapes and brands, and the queues (pending orders, open returns,
     * unapproved reviews) that need staff attention.
     */
    public function index(Request $request): View
    {
        $days = (int) $request->integer('days', 30);
        $since = now()->subDays($days);

        $revenueByDay = Order::where('created_at', '>=', $since)
            ->whereNotIn('status', self::NON_SALE_STATUSES)
            ->select(DB::raw('date(created_at) as day'), DB::raw('sum(total) as revenue'), DB::raw('count(*) as orders'))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $topFramesBySales = OrderEyeglass::join('orders', 'orders.id', '=', 'order_eyeglasses.order_id')
            ->where('orders.created_at', '>=', $since)
            ->whereNotIn('orders.status', self::NON_SALE_STATUSES)
            ->select('frame_id', 'frame_name', DB::raw('sum(order_eyeglasses.quantity) as units_sold'), DB::raw('sum(order_eyeglasses.line_total) as revenue'))
            ->groupBy('frame_id', 'frame_name')
            ->orderByDesc('units_sold')
            ->limit(10)
            ->get();

        $mostViewedFrames = ProductView::where('viewable_type', Frame::class)
            ->where('created_at', '>=', $since)
            ->select('viewable_id', DB::raw('count(*) as views'))
            ->groupBy('viewable_id')
            ->orderByDesc('views')
            ->limit(10)
            ->get();

        $frameNames = Frame::whereIn('id', $mostViewedFrames->pluck('viewable_id'))->pluck('name', 'id');

        return view('admin.dashboard', [
            'days' => $days,
            'revenueByDay' => $revenueByDay,
            'totalRevenue' => $revenueByDay->sum('revenue'),
            'totalOrders' => $revenueByDay->sum('orders'),
            'topFramesBySales' => $topFramesBySales,
            'mostViewedFrames' => $mostViewedFrames->map(fn ($row) => [
                'name' => $frameNames[$row->viewable_id] ?? 'Deleted frame',
                'views' => $row->views,
            ]),
            'lowStockFrames' => Frame::where('is_active', true)->where('stock', '<=', Frame::LOW_STOCK_THRESHOLD)->orderBy('stock')->get(),
            'lowStockContactLenses' => ContactLens::where('is_active', true)->where('stock', '<=', ContactLens::LOW_STOCK_THRESHOLD)->orderBy('stock')->get(),
            'pendingOrdersCount' => Order::where('status', 'pending')->count(),
            'openReturnsCount' => OrderReturn::whereIn('status', ['requested', 'approved'])->count(),
            'pendingReviewsCount' => Review::where('is_approved', false)->count(),

            'framesSoldShare' => $this->framesSoldShare($since),
            'salesMix' => $this->salesMix($since),
            'stockByCategory' => $this->stockByCategory(),
            'stockHealth' => $this->stockHealth(),
            'ordersByStatus' => $this->ordersByStatus($since),
            'unitsByShape' => $this->unitsByShape($since),
            'topBrands' => $this->topBrands($since),
            'ratingDistribution' => $this->ratingDistribution(),
            'ordersByWeekday' => $this->ordersByWeekday($revenueByDay),
            'staffCount' => User::where('role', 'staff')->count(),
            'deliveryCount' => User::where('role', 'delivery')->count(),
        ]);
    }

    /**
     * Pie slices for "which frames people actually buy": the six best sellers
     * by units, with the long tail collapsed into one slice so the shares
     * still add up to every frame sold in the window.
     */
    private function framesSoldShare(Carbon $since): array
    {
        $rows = OrderEyeglass::join('orders', 'orders.id', '=', 'order_eyeglasses.order_id')
            ->where('orders.created_at', '>=', $since)
            ->whereNotIn('orders.status', self::NON_SALE_STATUSES)
            ->select('frame_name', DB::raw('sum(order_eyeglasses.quantity) as units'))
            ->groupBy('frame_name')
            ->orderByDesc('units')
            ->get();

        $segments = $rows->take(6)->map(fn ($row) => [
            'label' => $row->frame_name,
            'value' => (int) $row->units,
        ])->all();

        $tail = (int) $rows->skip(6)->sum('units');

        if ($tail > 0) {
            $segments[] = ['label' => __('Other frames'), 'value' => $tail];
        }

        return [
            'segments' => $segments,
            'total' => (int) $rows->sum('units'),
            'distinct' => $rows->count(),
        ];
    }

    /**
     * Revenue and units split across the two product lines, so the owner can
     * see whether the shop is carried by eyeglasses or by contact lenses.
     */
    private function salesMix(Carbon $since): array
    {
        $eyeglasses = OrderEyeglass::join('orders', 'orders.id', '=', 'order_eyeglasses.order_id')
            ->where('orders.created_at', '>=', $since)
            ->whereNotIn('orders.status', self::NON_SALE_STATUSES)
            ->selectRaw('coalesce(sum(order_eyeglasses.line_total), 0) as revenue, coalesce(sum(order_eyeglasses.quantity), 0) as units')
            ->first();

        $contactLenses = OrderContactLens::join('orders', 'orders.id', '=', 'order_contact_lenses.order_id')
            ->where('orders.created_at', '>=', $since)
            ->whereNotIn('orders.status', self::NON_SALE_STATUSES)
            ->selectRaw('coalesce(sum(order_contact_lenses.line_total), 0) as revenue, coalesce(sum(order_contact_lenses.quantity), 0) as units')
            ->first();

        return [
            ['label' => __('Eyeglasses'), 'value' => (float) $eyeglasses->revenue, 'units' => (int) $eyeglasses->units],
            ['label' => __('Contact lenses'), 'value' => (float) $contactLenses->revenue, 'units' => (int) $contactLenses->units],
        ];
    }

    /**
     * Units sitting on the shelf per frame category, plus the contact-lens
     * pool: what is actually available to sell right now.
     */
    private function stockByCategory(): array
    {
        $rows = Frame::where('is_active', true)
            ->select('category', DB::raw('sum(stock) as units'), DB::raw('count(*) as models'))
            ->groupBy('category')
            ->orderByDesc('units')
            ->get()
            ->map(fn ($row) => [
                'label' => __(ucfirst($row->category)),
                'value' => (int) $row->units,
                'meta' => $row->units.' '.Str::plural('unit', $row->units).' · '.$row->models.' '.Str::plural('model', $row->models),
            ])
            ->all();

        $lenses = ContactLens::where('is_active', true)
            ->selectRaw('coalesce(sum(stock), 0) as units, count(*) as models')
            ->first();

        $rows[] = [
            'label' => __('Contact lenses'),
            'value' => (int) $lenses->units,
            'meta' => $lenses->units.' '.Str::plural('unit', $lenses->units).' · '.$lenses->models.' '.Str::plural('model', $lenses->models),
        ];

        return $rows;
    }

    /**
     * Every active product bucketed as healthy / running low / out of stock,
     * each catalogue judged against its own low-stock line.
     */
    private function stockHealth(): array
    {
        $frames = Frame::where('is_active', true)
            ->selectRaw('sum(case when stock = 0 then 1 else 0 end) as out_of_stock')
            ->selectRaw('sum(case when stock > 0 and stock <= ? then 1 else 0 end) as low', [Frame::LOW_STOCK_THRESHOLD])
            ->selectRaw('sum(case when stock > ? then 1 else 0 end) as healthy', [Frame::LOW_STOCK_THRESHOLD])
            ->selectRaw('coalesce(sum(stock), 0) as units')
            ->first();

        $lenses = ContactLens::where('is_active', true)
            ->selectRaw('sum(case when stock = 0 then 1 else 0 end) as out_of_stock')
            ->selectRaw('sum(case when stock > 0 and stock <= ? then 1 else 0 end) as low', [ContactLens::LOW_STOCK_THRESHOLD])
            ->selectRaw('sum(case when stock > ? then 1 else 0 end) as healthy', [ContactLens::LOW_STOCK_THRESHOLD])
            ->selectRaw('coalesce(sum(stock), 0) as units')
            ->first();

        return [
            'segments' => [
                ['label' => __('Healthy'), 'value' => (int) $frames->healthy + (int) $lenses->healthy],
                ['label' => __('Running low'), 'value' => (int) $frames->low + (int) $lenses->low],
                ['label' => __('Out of stock'), 'value' => (int) $frames->out_of_stock + (int) $lenses->out_of_stock],
            ],
            'units' => (int) $frames->units + (int) $lenses->units,
        ];
    }

    /**
     * Where the orders in the window currently sit. Held in lifecycle order
     * rather than sorted by size, so the column reads like a pipeline.
     */
    private function ordersByStatus(Carbon $since): array
    {
        $counts = Order::where('created_at', '>=', $since)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $lifecycle = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];

        return collect($lifecycle)
            ->map(fn ($status) => [
                'label' => __(ucfirst($status)),
                'status' => $status,
                'value' => (int) ($counts[$status] ?? 0),
            ])
            ->filter(fn ($row) => $row['value'] > 0)
            ->values()
            ->all();
    }

    /**
     * Units sold per frame outline shape — the merchandising view that says
     * which silhouettes to reorder, and which ones the face-match tool is
     * steering customers towards.
     */
    private function unitsByShape(Carbon $since): array
    {
        return OrderEyeglass::join('orders', 'orders.id', '=', 'order_eyeglasses.order_id')
            ->join('frames', 'frames.id', '=', 'order_eyeglasses.frame_id')
            ->where('orders.created_at', '>=', $since)
            ->whereNotIn('orders.status', self::NON_SALE_STATUSES)
            ->whereNotNull('frames.shape')
            ->select('frames.shape', DB::raw('sum(order_eyeglasses.quantity) as units'))
            ->groupBy('frames.shape')
            ->orderByDesc('units')
            ->get()
            ->map(fn ($row) => [
                'label' => __(ucfirst(str_replace('_', ' ', $row->shape))),
                'value' => (int) $row->units,
                'meta' => $row->units.' '.Str::plural('unit', $row->units),
            ])
            ->all();
    }

    /** Which brands bring in the money, by frame revenue in the window. */
    private function topBrands(Carbon $since): array
    {
        return OrderEyeglass::join('orders', 'orders.id', '=', 'order_eyeglasses.order_id')
            ->where('orders.created_at', '>=', $since)
            ->whereNotIn('orders.status', self::NON_SALE_STATUSES)
            ->whereNotNull('frame_brand')
            ->select('frame_brand', DB::raw('sum(order_eyeglasses.line_total) as revenue'), DB::raw('sum(order_eyeglasses.quantity) as units'))
            ->groupBy('frame_brand')
            ->orderByDesc('revenue')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->frame_brand,
                'value' => (float) $row->revenue,
                'meta' => '$'.number_format((float) $row->revenue, 2).' · '.$row->units.' '.Str::plural('unit', $row->units),
            ])
            ->all();
    }

    /**
     * Star histogram over all approved reviews rather than the window — a
     * rating average only means something across the whole standing record.
     */
    private function ratingDistribution(): array
    {
        $counts = Review::where('is_approved', true)
            ->select('rating', DB::raw('count(*) as total'))
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $rows = collect(range(5, 1))->map(fn ($star) => [
            'stars' => $star,
            'value' => (int) ($counts[$star] ?? 0),
        ])->all();

        $total = array_sum(array_column($rows, 'value'));
        $weighted = array_sum(array_map(fn ($row) => $row['stars'] * $row['value'], $rows));

        return [
            'rows' => $rows,
            'total' => $total,
            'average' => $total > 0 ? round($weighted / $total, 2) : null,
        ];
    }

    /**
     * Which weekdays the shop actually sells on. Folded in PHP from the
     * per-day totals already fetched, so it costs no extra query and stays
     * driver-agnostic (MySQL and SQLite spell weekday extraction differently).
     */
    private function ordersByWeekday(iterable $revenueByDay): array
    {
        $buckets = array_fill(0, 7, ['orders' => 0, 'revenue' => 0.0]);

        foreach ($revenueByDay as $row) {
            $index = Carbon::parse($row->day)->dayOfWeek;
            $buckets[$index]['orders'] += (int) $row->orders;
            $buckets[$index]['revenue'] += (float) $row->revenue;
        }

        $sunday = Carbon::now()->startOfWeek(Carbon::SUNDAY);

        return collect($buckets)
            ->map(fn ($bucket, $index) => [
                'label' => $sunday->copy()->addDays($index)->format('D'),
                'value' => $bucket['orders'],
                'revenue' => $bucket['revenue'],
            ])
            ->values()
            ->all();
    }
}
