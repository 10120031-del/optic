<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The order's progress is the shop owner's to set, and — because payment is
 * cash on delivery — so is the moment the money is recorded as received.
 */
class AdminOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    private function pendingOrder(): Order
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'order_number' => 'OPT-'.strtoupper(uniqid()),
            'status' => 'pending',
            'subtotal' => 119.00,
            'shipping_cost' => 5.00,
            'tax' => 0,
            'total' => 124.00,
            'shipping_address_line' => '12 Rue Verdun',
            'shipping_city' => 'Beirut',
            'shipping_country' => 'Lebanon',
        ]);

        $order->payments()->create([
            'method' => Payment::METHOD_CASH_ON_DELIVERY,
            'status' => Payment::STATUS_PENDING,
            'amount' => 124.00,
        ]);

        return $order;
    }

    private function update(Order $order, array $data): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.orders.status', $order), $data);
    }

    public function test_owner_moves_an_order_through_the_pipeline(): void
    {
        $order = $this->pendingOrder();

        foreach (['processing', 'shipped', 'delivered'] as $status) {
            $this->update($order, ['status' => $status])->assertRedirect();
        }

        $order->refresh();

        $this->assertSame('delivered', $order->status);
        $this->assertNotNull($order->shipped_at);
        $this->assertNotNull($order->delivered_at);

        // Every hop is on the customer's timeline, attributed to the staff member.
        $this->assertSame(
            ['processing', 'shipped', 'delivered'],
            $order->statusHistory()->pluck('status')->all(),
        );
        $this->assertNotNull($order->statusHistory()->first()->changed_by);
    }

    public function test_marking_an_order_delivered_records_the_cash_as_collected(): void
    {
        $order = $this->pendingOrder();

        $this->update($order, ['status' => 'delivered']);

        $payment = $order->payments()->sole();

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertNotNull($order->refresh()->paid_at);
    }

    public function test_marking_an_order_paid_settles_it_before_delivery(): void
    {
        $order = $this->pendingOrder();

        $this->update($order, ['status' => 'paid', 'note' => 'Courier handed the cash in.']);

        $this->assertSame(Payment::STATUS_COMPLETED, $order->payments()->sole()->status);
        $this->assertSame('Courier handed the cash in.', $order->statusHistory()->latest('id')->first()->note);
    }

    public function test_cancelling_closes_out_the_uncollected_cash(): void
    {
        $order = $this->pendingOrder();

        $this->update($order, ['status' => 'cancelled']);

        $this->assertSame(Payment::STATUS_FAILED, $order->payments()->sole()->status);
        $this->assertNull($order->refresh()->paid_at);
        $this->assertNotNull($order->cancelled_at);
    }

    public function test_refunding_a_delivered_order_reverses_the_payment(): void
    {
        $order = $this->pendingOrder();

        $this->update($order, ['status' => 'delivered']);
        $this->update($order, ['status' => 'refunded']);

        $this->assertSame(Payment::STATUS_REFUNDED, $order->payments()->sole()->status);
    }

    public function test_shipping_details_are_recorded_with_the_status(): void
    {
        $order = $this->pendingOrder();

        $this->update($order, [
            'status' => 'shipped',
            'carrier' => 'Aramex',
            'tracking_number' => 'ARX-99',
            'estimated_delivery_date' => '2026-09-05',
        ]);

        $order->refresh();

        $this->assertSame('Aramex', $order->carrier);
        $this->assertSame('ARX-99', $order->tracking_number);
        $this->assertSame('2026-09-05', $order->estimated_delivery_date->format('Y-m-d'));
    }

    public function test_a_customer_cannot_move_their_own_order_along(): void
    {
        $order = $this->pendingOrder();

        $this->actingAs(User::factory()->create())
            ->patch(route('admin.orders.status', $order), ['status' => 'delivered'])
            ->assertForbidden();

        $this->assertSame('pending', $order->refresh()->status);
        $this->assertSame(Payment::STATUS_PENDING, $order->payments()->sole()->status);
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $order = $this->pendingOrder();

        $this->update($order, ['status' => 'lost_in_transit'])
            ->assertSessionHasErrors('status');

        $this->assertSame('pending', $order->refresh()->status);
    }
}
