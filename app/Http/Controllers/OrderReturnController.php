<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderContactLens;
use App\Models\OrderEyeglass;
use App\Models\OrderReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderReturnController extends Controller
{
    public function create(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        abort_unless(in_array($order->status, ['delivered', 'shipped'], true), 422, 'This order isn\'t eligible for a return yet.');

        $order->load(['eyeglasses', 'contactLenses']);

        return view('orders.returns.create', ['order' => $order]);
    }

    /**
     * File a return/exchange request against specific line(s) of a
     * delivered order — not necessarily the whole order. Starts at
     * status "requested"; staff take it from there (see
     * Admin\OrderReturnController).
     */
    public function store(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'type' => ['required', 'in:return,exchange'],
            'reason' => ['required', 'in:wrong_prescription,wrong_size_fit,damaged_or_defective,not_as_described,changed_mind,other'],
            'reason_details' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'in:eyeglass,contact_lens'],
            'items.*.id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.condition_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $orderReturn = OrderReturn::create([
            'order_id' => $order->id,
            'requested_by' => $request->user()->id,
            'type' => $data['type'],
            'reason' => $data['reason'],
            'reason_details' => $data['reason_details'] ?? null,
            'status' => 'requested',
        ]);

        foreach ($data['items'] as $item) {
            $returnableClass = $item['type'] === 'eyeglass' ? OrderEyeglass::class : OrderContactLens::class;

            // Confirm the line actually belongs to this order before
            // attaching it to the return.
            abort_unless($returnableClass::where('id', $item['id'])->where('order_id', $order->id)->exists(), 422);

            $orderReturn->items()->create([
                'returnable_type' => $returnableClass,
                'returnable_id' => $item['id'],
                'quantity' => $item['quantity'],
                'condition_notes' => $item['condition_notes'] ?? null,
            ]);
        }

        return redirect()->route('orders.show', $order)->with('status', 'Return request submitted — we\'ll follow up by email.');
    }
}
