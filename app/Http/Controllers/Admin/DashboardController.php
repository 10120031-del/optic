<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactLens;
use App\Models\Frame;
use App\Models\Order;
use App\Models\OrderEyeglass;
use App\Models\OrderReturn;
use App\Models\ProductView;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Requirement 6's "track trends and statistics": revenue over time,
     * best sellers by units and by view-to-purchase interest, low stock,
     * and the queues (pending orders, open returns, unapproved reviews)
     * that need staff attention right now.
     */
    public function index(Request $request): View
    {
        $days = (int) $request->integer('days', 30);
        $since = now()->subDays($days);

        $revenueByDay = Order::where('created_at', '>=', $since)
            ->whereNotIn('status', ['cancelled'])
            ->select(DB::raw('date(created_at) as day'), DB::raw('sum(total) as revenue'), DB::raw('count(*) as orders'))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $topFramesBySales = OrderEyeglass::join('orders', 'orders.id', '=', 'order_eyeglasses.order_id')
            ->where('orders.created_at', '>=', $since)
            ->whereNotIn('orders.status', ['cancelled'])
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
            'lowStockFrames' => Frame::where('is_active', true)->where('stock', '<=', 5)->orderBy('stock')->get(),
            'lowStockContactLenses' => ContactLens::where('is_active', true)->where('stock', '<=', 10)->orderBy('stock')->get(),
            'pendingOrdersCount' => Order::where('status', 'pending')->count(),
            'openReturnsCount' => OrderReturn::whereIn('status', ['requested', 'approved'])->count(),
            'pendingReviewsCount' => Review::where('is_approved', false)->count(),
        ]);
    }
}
