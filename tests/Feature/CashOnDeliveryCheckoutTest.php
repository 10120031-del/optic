<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Frame;
use App\Models\Lens;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shop takes cash on delivery and nothing else: checkout never asks for a
 * payment method, never marks an order paid, and the money is only recorded
 * once the owner says it arrived.
 */
class CashOnDeliveryCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function customerWithFullCart(): User
    {
        $user = User::factory()->create();

        $frame = Frame::create([
            'name' => 'Harbor Classic', 'brand' => 'OPTIX', 'sku' => 'SKU-'.uniqid(),
            'lens_width' => 50, 'lens_height' => 40, 'bridge_width' => 18, 'temple_length' => 140,
            'material' => 'acetate', 'category' => 'eyeglasses', 'type' => 'full_rim',
            'gender' => 'unisex', 'price' => 89.00, 'stock' => 5, 'is_active' => true,
        ]);

        $lens = Lens::create([
            'name' => 'Standard single vision', 'material' => 'plastic',
            'type' => 'single_vision', 'refractive_index' => 1.50,
            'price' => 30.00, 'is_active' => true,
        ]);

        Cart::create(['user_id' => $user->id])
            ->eyeglasses()
            ->create(['frame_id' => $frame->id, 'lens_id' => $lens->id, 'quantity' => 1]);

        return $user;
    }

    private array $shipping = [
        'shipping_address_line' => '12 Rue Verdun',
        'shipping_city' => 'Beirut',
        'shipping_postal_code' => '1107',
        'shipping_country' => 'Lebanon',
    ];

    public function test_checkout_page_offers_no_payment_choice(): void
    {
        $response = $this->actingAs($this->customerWithFullCart())->get(route('checkout.index'));

        $response->assertOk();
        $response->assertSee('Cash on delivery');
        $response->assertDontSee('PayPal');
        $response->assertDontSee('name="payment_method"', false);
    }

    public function test_placing_an_order_records_a_pending_cash_payment(): void
    {
        $user = $this->customerWithFullCart();

        $this->actingAs($user)->post(route('checkout.store'), $this->shipping)
            ->assertRedirect();

        $order = Order::firstOrFail();

        // 89 frame + 30 lens + 5 shipping.
        $this->assertSame('124.00', $order->total);
        $this->assertSame('pending', $order->status);
        $this->assertNull($order->paid_at);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => Payment::METHOD_CASH_ON_DELIVERY,
            'status' => Payment::STATUS_PENDING,
            'amount' => '124.00',
            'paid_at' => null,
        ]);
    }

    public function test_a_posted_payment_method_cannot_pre_pay_an_order(): void
    {
        $user = $this->customerWithFullCart();

        $this->actingAs($user)
            ->post(route('checkout.store'), $this->shipping + ['payment_method' => 'card'])
            ->assertRedirect();

        $order = Order::firstOrFail();

        $this->assertSame('pending', $order->status);
        $this->assertSame(Payment::METHOD_CASH_ON_DELIVERY, $order->payments()->sole()->method);
    }

    public function test_the_order_page_tells_the_customer_what_to_have_ready(): void
    {
        $user = $this->customerWithFullCart();
        $this->actingAs($user)->post(route('checkout.store'), $this->shipping);

        $this->actingAs($user)
            ->get(route('orders.show', Order::firstOrFail()))
            ->assertSee('Have $124.00 ready for the courier', false);
    }
}
