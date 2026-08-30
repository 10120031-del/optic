<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderReturn;
use App\Models\OrderStatusHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrderReturnController extends Controller
{
    public function index(Request $request): View
    {
        $returns = OrderReturn::query()
            ->with(['order.user', 'requestedBy'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.returns.index', ['returns' => $returns]);
    }

    public function show(OrderReturn $return): View
    {
        $return->load(['order.user', 'requestedBy', 'items.returnable']);

        return view('admin.returns.show', ['return' => $return]);
    }

    /**
     * Approve, reject, mark received, and settle a return/exchange. A
     * refund transitions the return to "refunded" and writes a matching
     * refund Payment row against the original order; an approved exchange
     * expects staff to have already created the replacement order and
     * link it via exchange_order_id.
     */
    public function updateStatus(Request $request, OrderReturn $return): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:requested,approved,rejected,item_received,refunded,exchanged'],
            'staff_notes' => ['nullable', 'string', 'max:2000'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'exchange_order_id' => ['nullable', 'exists:orders,id'],
        ]);

        DB::transaction(function () use ($request, $return, $data) {
            $return->update([
                'status' => $data['status'],
                'staff_notes' => $data['staff_notes'] ?? $return->staff_notes,
                'refund_amount' => $data['refund_amount'] ?? $return->refund_amount,
                'exchange_order_id' => $data['exchange_order_id'] ?? $return->exchange_order_id,
                'resolved_by' => in_array($data['status'], ['rejected', 'refunded', 'exchanged'], true) ? $request->user()->id : $return->resolved_by,
                'resolved_at' => in_array($data['status'], ['rejected', 'refunded', 'exchanged'], true) ? now() : $return->resolved_at,
            ]);

            $refundAmount = $data['refund_amount'] ?? null;

            if ($data['status'] === 'refunded' && $refundAmount !== null) {
                $return->order->payments()->create([
                    'method' => 'bank_transfer',
                    'status' => 'refunded',
                    'amount' => $refundAmount,
                    'paid_at' => now(),
                    'notes' => 'Refund for return #'.$return->id,
                ]);

                $return->order->update(['status' => 'refunded']);

                OrderStatusHistory::create([
                    'order_id' => $return->order_id,
                    'status' => 'refunded',
                    'note' => 'Refunded via return #'.$return->id,
                    'changed_by' => $request->user()->id,
                ]);
            }
        });

        return back()->with('status', 'Return updated.');
    }
}
