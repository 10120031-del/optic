<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Recommender;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(private readonly Recommender $recommender)
    {
    }

    public function index(Request $request): View
    {
        $orders = $request->user()->orders()->latest()->paginate(15);

        return view('orders.index', ['orders' => $orders]);
    }

    /**
     * The customer's order-tracking page: current status plus the full
     * order_status_history timeline (requirement 5), and any return
     * requests filed against it.
     */
    public function show(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $order->load([
            'eyeglasses.frame',
            'eyeglasses.lens',
            'eyeglasses.features',
            'contactLenses',
            'statusHistory.changedBy',
            'payments',
            'returns.items',
        ]);

        return view('orders.show', [
            'order' => $order,
            // "You bought X — you might like Y". Null when the order's
            // products have nothing to pair with yet; the view skips it.
            'related' => $this->recommender->relatedToOrder($order),
        ]);
    }
}
