<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrderController extends Controller
{
    private const STATUSES = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];

    public function __construct(private readonly InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $orders = Order::query()
            ->with('user')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('q'), fn ($q) => $q->whereLike('order_number', '%'.$request->string('q')->toString().'%'))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.orders.index', ['orders' => $orders, 'statuses' => self::STATUSES]);
    }

    public function show(Order $order): View
    {
        $order->load(['user', 'eyeglasses.frame', 'eyeglasses.lens', 'eyeglasses.features', 'contactLenses', 'payments', 'statusHistory.changedBy', 'returns.items']);

        return view('admin.orders.show', ['order' => $order, 'statuses' => self::STATUSES]);
    }

    /**
     * Requirement 6: staff mark orders shipped/delivered/etc. Every change
     * lands in order_status_history so the customer's tracking page (and
     * this order's audit trail) stays complete.
     */
    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', self::STATUSES)],
            'note' => ['nullable', 'string', 'max:1000'],
            'carrier' => ['nullable', 'string', 'max:255'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'estimated_delivery_date' => ['nullable', 'date'],
        ]);

        $timestampField = match ($data['status']) {
            'paid' => 'paid_at',
            'shipped' => 'shipped_at',
            'delivered' => 'delivered_at',
            'cancelled' => 'cancelled_at',
            default => null,
        };

        $changes = [
            'status' => $data['status'],
            'carrier' => $data['carrier'] ?? $order->carrier,
            'tracking_number' => $data['tracking_number'] ?? $order->tracking_number,
            'estimated_delivery_date' => $data['estimated_delivery_date'] ?? $order->estimated_delivery_date,
        ];

        if ($timestampField) {
            $changes[$timestampField] = now();
        }

        // The status, the payment, the audit row and the stock that goes back
        // on the shelf are one change, not four — a failure part way through
        // must not leave an order cancelled with its units still spent.
        DB::transaction(function () use ($request, $order, $data, $changes) {
            $wasCancelled = $order->status === 'cancelled';

            $order->update($changes);

            $this->settlePayments($order, $data['status']);

            // A cancelled order never shipped, so its units go back. Guarded on
            // the previous status so re-saving an already-cancelled order
            // cannot restock it twice.
            if ($data['status'] === 'cancelled' && ! $wasCancelled) {
                $this->inventory->restore($order);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => $data['status'],
                'note' => $data['note'] ?? null,
                'changed_by' => $request->user()->id,
            ]);
        });

        return back()->with('status', 'Order updated.');
    }

    /**
     * Keep the payment record in step with the status the owner just set.
     *
     * Everything is cash on delivery, so there is no gateway callback to tell
     * us the money arrived — the owner marking the order paid or delivered
     * *is* the confirmation that the courier collected it. Doing it here
     * rather than in a separate screen means staff can't leave an order
     * delivered with its payment still showing as outstanding.
     */
    private function settlePayments(Order $order, string $status): void
    {
        if (in_array($status, ['paid', 'delivered'], true)) {
            $order->payments()
                ->where('status', Payment::STATUS_PENDING)
                ->update(['status' => Payment::STATUS_COMPLETED, 'paid_at' => now()]);

            // 'delivered' sets delivered_at above but not paid_at, and an
            // order can be delivered without ever passing through 'paid'.
            if (! $order->paid_at) {
                $order->update(['paid_at' => now()]);
            }
        }

        // Nothing was ever collected on a cancelled order, so close the
        // pending payment out rather than leaving it owing forever.
        if ($status === 'cancelled') {
            $order->payments()
                ->where('status', Payment::STATUS_PENDING)
                ->update(['status' => Payment::STATUS_FAILED]);
        }

        if ($status === 'refunded') {
            $order->payments()
                ->where('status', Payment::STATUS_COMPLETED)
                ->update(['status' => Payment::STATUS_REFUNDED]);
        }
    }
}
