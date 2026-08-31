<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\ContactLens;
use App\Models\Frame;
use App\Models\Lens;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stock comes off the shelf when the order is written — not when something is
 * dropped in a cart — and goes back on if the order is cancelled. The shop
 * would rather turn a shopper away at checkout than sell a frame it does not
 * have, so the count is re-checked under a lock at the last moment.
 */
class CheckoutStockTest extends TestCase
{
    use RefreshDatabase;

    private array $shipping = [
        'shipping_address_line' => '12 Rue Verdun',
        'shipping_city' => 'Beirut',
        'shipping_postal_code' => '1107',
        'shipping_country' => 'Lebanon',
    ];

    private function frame(int $stock, string $name = 'Harbor Classic'): Frame
    {
        return Frame::create([
            'name' => $name, 'brand' => 'Optix', 'sku' => 'SKU-'.uniqid(),
            'lens_width' => 50, 'lens_height' => 40, 'bridge_width' => 18, 'temple_length' => 140,
            'material' => 'acetate', 'category' => 'eyeglasses', 'type' => 'full_rim',
            'gender' => 'unisex', 'price' => 89.00, 'stock' => $stock, 'is_active' => true,
        ]);
    }

    private function contactLens(int $stock): ContactLens
    {
        return ContactLens::create([
            'name' => 'DailyClear', 'brand' => 'VisionPlus', 'sku' => 'CL-'.uniqid(),
            'type' => 'daily', 'material' => 'silicone_hydrogel', 'pack_size' => 30,
            'price' => 32.00, 'stock' => $stock, 'is_active' => true,
        ]);
    }

    private function lens(): Lens
    {
        return Lens::create([
            'name' => 'Standard single vision', 'material' => 'plastic',
            'type' => 'single_vision', 'refractive_index' => 1.50,
            'price' => 30.00, 'is_active' => true,
        ]);
    }

    private function cartFor(User $user): Cart
    {
        return Cart::firstOrCreate(['user_id' => $user->id]);
    }

    public function test_placing_an_order_takes_the_units_off_the_shelf(): void
    {
        $user = User::factory()->create();
        $frame = $this->frame(5);
        $lenses = $this->contactLens(40);

        $cart = $this->cartFor($user);
        $cart->eyeglasses()->create(['frame_id' => $frame->id, 'lens_id' => $this->lens()->id, 'quantity' => 2]);
        $cart->contactLenses()->create(['contact_lens_id' => $lenses->id, 'quantity' => 3]);

        $this->actingAs($user)->post(route('checkout.store'), $this->shipping)->assertRedirect();

        $this->assertSame(3, $frame->refresh()->stock);
        $this->assertSame(37, $lenses->refresh()->stock);
    }

    public function test_the_same_frame_on_several_lines_is_counted_once_in_total(): void
    {
        $user = User::factory()->create();
        // Two pairs of the same frame with different lenses is two frames off
        // the shelf, not one.
        $frame = $this->frame(2);

        $cart = $this->cartFor($user);
        $cart->eyeglasses()->create(['frame_id' => $frame->id, 'lens_id' => $this->lens()->id, 'quantity' => 1]);
        $cart->eyeglasses()->create(['frame_id' => $frame->id, 'lens_id' => $this->lens()->id, 'quantity' => 1]);

        $this->actingAs($user)->post(route('checkout.store'), $this->shipping)->assertRedirect();

        $this->assertSame(0, $frame->refresh()->stock);
    }

    public function test_a_cart_bigger_than_the_shelf_is_refused_and_nothing_is_written(): void
    {
        $user = User::factory()->create();
        $frame = $this->frame(1);

        $cart = $this->cartFor($user);
        $cart->eyeglasses()->create(['frame_id' => $frame->id, 'lens_id' => $this->lens()->id, 'quantity' => 3]);

        $this->actingAs($user)->post(route('checkout.store'), $this->shipping)
            ->assertSessionHasErrors('cart');

        // The whole transaction rolled back: no order, no payment, no stock moved.
        $this->assertSame(0, Order::count());
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(1, $frame->refresh()->stock);

        // And the cart is intact, so they can go back and reduce the quantity.
        $this->assertSame(1, $cart->eyeglasses()->count());
    }

    public function test_the_shopper_is_told_which_product_is_short(): void
    {
        $user = User::factory()->create();
        $frame = $this->frame(2, 'Willow Cat-Eye');

        $cart = $this->cartFor($user);
        $cart->eyeglasses()->create(['frame_id' => $frame->id, 'lens_id' => $this->lens()->id, 'quantity' => 4]);

        $this->actingAs($user)->post(route('checkout.store'), $this->shipping)
            ->assertSessionHasErrors(['cart' => 'Only 2 of Willow Cat-Eye left — your cart has 4.']);
    }

    public function test_a_sold_out_product_says_so(): void
    {
        $user = User::factory()->create();
        $frame = $this->frame(0, 'Denton Square');

        $cart = $this->cartFor($user);
        $cart->eyeglasses()->create(['frame_id' => $frame->id, 'lens_id' => $this->lens()->id, 'quantity' => 1]);

        $this->actingAs($user)->post(route('checkout.store'), $this->shipping)
            ->assertSessionHasErrors(['cart' => 'Denton Square has just sold out.']);
    }

    public function test_the_checkout_page_warns_before_the_order_is_attempted(): void
    {
        $user = User::factory()->create();
        $frame = $this->frame(1, 'Willow Cat-Eye');

        $cart = $this->cartFor($user);
        $cart->eyeglasses()->create(['frame_id' => $frame->id, 'lens_id' => $this->lens()->id, 'quantity' => 3]);

        $this->actingAs($user)->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Only 1 of Willow Cat-Eye left');
    }

    public function test_selling_the_last_units_alerts_the_owner(): void
    {
        $owner = User::factory()->admin()->create();
        $user = User::factory()->create();
        $frame = $this->frame(2, 'Pebble Oval');

        $cart = $this->cartFor($user);
        $cart->eyeglasses()->create(['frame_id' => $frame->id, 'lens_id' => $this->lens()->id, 'quantity' => 2]);

        $this->actingAs($user)->post(route('checkout.store'), $this->shipping)->assertRedirect();

        // Selling through the shelf is exactly what the stock observer watches
        // for, and it now fires from a real sale rather than a manual edit.
        $this->assertSame(
            'Out of stock: Pebble Oval',
            $owner->notifications()->where('data->event', 'admin.stock.out')->sole()->data['title'],
        );
    }

    public function test_cancelling_an_order_puts_the_units_back(): void
    {
        $owner = User::factory()->admin()->create();
        $user = User::factory()->create();
        $frame = $this->frame(5);
        $lenses = $this->contactLens(40);

        $cart = $this->cartFor($user);
        $cart->eyeglasses()->create(['frame_id' => $frame->id, 'lens_id' => $this->lens()->id, 'quantity' => 2]);
        $cart->contactLenses()->create(['contact_lens_id' => $lenses->id, 'quantity' => 3]);

        $this->actingAs($user)->post(route('checkout.store'), $this->shipping);
        $order = Order::firstOrFail();

        $this->actingAs($owner)->patch(route('admin.orders.status', $order), ['status' => 'cancelled'])
            ->assertRedirect();

        $this->assertSame(5, $frame->refresh()->stock);
        $this->assertSame(40, $lenses->refresh()->stock);
    }

    public function test_re_saving_a_cancelled_order_does_not_restock_it_twice(): void
    {
        $owner = User::factory()->admin()->create();
        $user = User::factory()->create();
        $frame = $this->frame(5);

        $cart = $this->cartFor($user);
        $cart->eyeglasses()->create(['frame_id' => $frame->id, 'lens_id' => $this->lens()->id, 'quantity' => 2]);

        $this->actingAs($user)->post(route('checkout.store'), $this->shipping);
        $order = Order::firstOrFail();

        $this->actingAs($owner)->patch(route('admin.orders.status', $order), ['status' => 'cancelled']);
        $this->actingAs($owner)->patch(route('admin.orders.status', $order), ['status' => 'cancelled', 'note' => 'Confirmed with the customer.']);

        $this->assertSame(5, $frame->refresh()->stock);
    }

    public function test_a_refund_leaves_the_shelf_alone(): void
    {
        $owner = User::factory()->admin()->create();
        $user = User::factory()->create();
        $frame = $this->frame(5);

        $cart = $this->cartFor($user);
        $cart->eyeglasses()->create(['frame_id' => $frame->id, 'lens_id' => $this->lens()->id, 'quantity' => 2]);

        $this->actingAs($user)->post(route('checkout.store'), $this->shipping);
        $order = Order::firstOrFail();

        $this->actingAs($owner)->patch(route('admin.orders.status', $order), ['status' => 'delivered']);
        $this->actingAs($owner)->patch(route('admin.orders.status', $order), ['status' => 'refunded']);

        // The goods are back with the shop but nobody has checked their
        // condition yet — restocking is the owner's call, by hand.
        $this->assertSame(3, $frame->refresh()->stock);
    }
}
