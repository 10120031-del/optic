<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Services\CartService;
use App\Services\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
        private readonly PricingService $pricing,
    ) {
    }

    public function index(Request $request): View
    {
        $cart = $this->carts->current($request);
        $cart->load(['eyeglasses.frame', 'eyeglasses.lens', 'eyeglasses.features', 'contactLenses.contactLens']);

        abort_if($cart->eyeglasses->isEmpty() && $cart->contactLenses->isEmpty(), 422, 'Your cart is empty.');

        $subtotal = $this->pricing->cartSubtotal($cart->eyeglasses, $cart->contactLenses);

        return view('checkout.index', [
            'cart' => $cart,
            'totals' => $this->pricing->orderTotals($subtotal, shippingCost: 5.00, taxRate: 0.0),
        ]);
    }

    /**
     * Converts the cart into an order: snapshots every line's product
     * details and price (so future catalog edits never rewrite past
     * invoices — see order_eyeglasses/order_contact_lenses), records a
     * payment, and starts the order_status_history timeline.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shipping_address_line' => ['required', 'string', 'max:255'],
            'shipping_city' => ['required', 'string', 'max:255'],
            'shipping_postal_code' => ['nullable', 'string', 'max:20'],
            'shipping_country' => ['required', 'string', 'max:100'],
            'payment_method' => ['required', 'in:card,paypal,cash_on_delivery,bank_transfer'],
        ]);

        $cart = $this->carts->current($request);
        $cart->load(['eyeglasses.frame', 'eyeglasses.lens', 'eyeglasses.features', 'contactLenses.contactLens']);

        abort_if($cart->eyeglasses->isEmpty() && $cart->contactLenses->isEmpty(), 422, 'Your cart is empty.');

        $subtotal = $this->pricing->cartSubtotal($cart->eyeglasses, $cart->contactLenses);
        $totals = $this->pricing->orderTotals($subtotal, shippingCost: 5.00, taxRate: 0.0);

        $order = DB::transaction(function () use ($request, $data, $cart, $totals) {
            $order = Order::create([
                'user_id' => $request->user()->id,
                'order_number' => 'OPT-'.strtoupper(Str::random(8)),
                'status' => 'pending',
                ...$totals,
                'shipping_address_line' => $data['shipping_address_line'],
                'shipping_city' => $data['shipping_city'],
                'shipping_postal_code' => $data['shipping_postal_code'] ?? null,
                'shipping_country' => $data['shipping_country'],
            ]);

            foreach ($cart->eyeglasses as $line) {
                $featuresTotal = $line->features->sum(fn ($f) => (float) $f->price);

                $orderEyeglass = $order->eyeglasses()->create([
                    'frame_id' => $line->frame_id,
                    'lens_id' => $line->lens_id,
                    'prescription_id' => $line->prescription_id,
                    'quantity' => $line->quantity,
                    'frame_name' => $line->frame->name,
                    'frame_brand' => $line->frame->brand,
                    'lens_name' => $line->lens->name,
                    'frame_unit_price' => $line->frame->price,
                    'lens_unit_price' => $line->lens->price,
                    'features_unit_price' => $featuresTotal,
                    'line_total' => $this->pricing->eyeglassLineTotal($line),
                    'left_sphere' => $line->left_sphere,
                    'left_cylinder' => $line->left_cylinder,
                    'left_axis' => $line->left_axis,
                    'left_add' => $line->left_add,
                    'right_sphere' => $line->right_sphere,
                    'right_cylinder' => $line->right_cylinder,
                    'right_axis' => $line->right_axis,
                    'right_add' => $line->right_add,
                    'pd' => $line->pd,
                    'notes' => $line->notes,
                ]);

                foreach ($line->features as $feature) {
                    $orderEyeglass->features()->create([
                        'lens_feature_id' => $feature->id,
                        'feature_name' => $feature->name,
                        'unit_price' => $feature->price,
                    ]);
                }
            }

            foreach ($cart->contactLenses as $line) {
                $order->contactLenses()->create([
                    'contact_lens_id' => $line->contact_lens_id,
                    'quantity' => $line->quantity,
                    'product_name' => $line->contactLens->name,
                    'brand' => $line->contactLens->brand,
                    'unit_price' => $line->contactLens->price,
                    'line_total' => $this->pricing->contactLensLineTotal($line),
                    'left_power' => $line->left_power,
                    'right_power' => $line->right_power,
                    'left_cylinder' => $line->left_cylinder,
                    'right_cylinder' => $line->right_cylinder,
                    'left_axis' => $line->left_axis,
                    'right_axis' => $line->right_axis,
                ]);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'pending',
                'note' => 'Order placed.',
                'changed_by' => $request->user()->id,
            ]);

            // No real payment gateway is wired up yet. Card/PayPal are
            // recorded as completed so the flow is testable end to end;
            // swap this block for an actual Stripe/PayPal charge + webhook
            // before going live. Cash on delivery / bank transfer stay
            // pending until fulfilled manually by staff.
            $isPrepaid = in_array($data['payment_method'], ['card', 'paypal'], true);

            Payment::create([
                'order_id' => $order->id,
                'method' => $data['payment_method'],
                'status' => $isPrepaid ? 'completed' : 'pending',
                'amount' => $totals['total'],
                'paid_at' => $isPrepaid ? now() : null,
            ]);

            if ($isPrepaid) {
                $order->update(['status' => 'paid', 'paid_at' => now()]);
                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status' => 'paid',
                    'changed_by' => $request->user()->id,
                ]);
            }

            $cart->eyeglasses()->delete();
            $cart->contactLenses()->delete();

            return $order;
        });

        return redirect()->route('orders.show', $order)->with('status', 'Order placed — thank you!');
    }
}
