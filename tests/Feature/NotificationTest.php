<?php

namespace Tests\Feature;

use App\Models\ContactLens;
use App\Models\Frame;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Both inboxes: the customer hears about their own order, return,
 * prescription and review; the owner hears about anything needing a decision
 * or a restock. The observers in App\Observers do the fanning out, so these
 * tests drive the real HTTP routes rather than calling the Notifier directly.
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->admin()->create();
    }

    private function order(User $customer, string $status = 'pending'): Order
    {
        $order = Order::create([
            'user_id' => $customer->id,
            'order_number' => 'OPT-'.strtoupper(uniqid()),
            'status' => $status,
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

    private function frame(int $stock): Frame
    {
        return Frame::create([
            'name' => 'Meridian',
            'brand' => 'Lucent',
            'sku' => 'MER-'.uniqid(),
            'lens_width' => 52.0,
            'lens_height' => 40.0,
            'bridge_width' => 18.0,
            'temple_length' => 145.0,
            'material' => 'acetate',
            'category' => 'eyeglasses',
            'type' => 'full_rim',
            'gender' => 'unisex',
            'price' => 119.00,
            'stock' => $stock,
            'is_active' => true,
        ]);
    }

    /** @return array<int, string> The `event` key of each row in someone's inbox, newest first. */
    private function events(User $user): array
    {
        return $user->notifications()->get()->pluck('data.event')->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    */

    public function test_placing_an_order_tells_the_customer_and_the_owner(): void
    {
        $owner = $this->owner();
        $customer = User::factory()->create();

        $order = $this->order($customer);

        $this->assertContains('order.placed', $this->events($customer));
        $this->assertContains('admin.order.placed', $this->events($owner));

        $this->assertStringContainsString(
            $order->order_number,
            $customer->notifications()->where('data->event', 'order.placed')->sole()->data['title'],
        );
    }

    public function test_every_pipeline_hop_reaches_the_customer_with_the_shipping_details(): void
    {
        $owner = $this->owner();
        $customer = User::factory()->create();
        $order = $this->order($customer);

        foreach (['processing', 'shipped', 'delivered'] as $status) {
            $this->actingAs($owner)->patch(route('admin.orders.status', $order), [
                'status' => $status,
                'carrier' => 'Aramex',
                'tracking_number' => 'ARX-99',
            ])->assertRedirect();
        }

        $statusNotes = $customer->notifications()->where('data->event', 'order.status')->get();

        $this->assertCount(3, $statusNotes);
        $this->assertStringContainsString('ARX-99', $statusNotes->pluck('data.body')->implode(' '));
    }

    public function test_editing_only_the_tracking_number_is_not_worth_a_notification(): void
    {
        $owner = $this->owner();
        $customer = User::factory()->create();
        $order = $this->order($customer, 'shipped');

        $this->actingAs($owner)->patch(route('admin.orders.status', $order), [
            'status' => 'shipped',
            'tracking_number' => 'ARX-100',
        ]);

        $this->assertSame(0, $customer->notifications()->where('data->event', 'order.status')->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Returns
    |--------------------------------------------------------------------------
    */

    public function test_a_return_request_is_acknowledged_and_queued_for_the_owner(): void
    {
        $owner = $this->owner();
        $customer = User::factory()->create();
        $order = $this->order($customer, 'delivered');

        $return = OrderReturn::create([
            'order_id' => $order->id,
            'requested_by' => $customer->id,
            'type' => 'return',
            'reason' => 'wrong_size_fit',
            'status' => 'requested',
        ]);

        $this->assertContains('return.requested', $this->events($customer));
        $this->assertContains('admin.return.requested', $this->events($owner));

        $this->actingAs($owner)->patch(route('admin.returns.status', $return), [
            'status' => 'approved',
        ])->assertRedirect();

        $this->assertContains('return.status', $this->events($customer));
    }

    /*
    |--------------------------------------------------------------------------
    | Reviews and prescriptions
    |--------------------------------------------------------------------------
    */

    public function test_a_new_review_reaches_moderation_and_approval_reaches_the_reviewer(): void
    {
        $owner = $this->owner();
        $customer = User::factory()->create();
        $frame = $this->frame(20);

        $review = Review::create([
            'reviewable_type' => Frame::class,
            'reviewable_id' => $frame->id,
            'user_id' => $customer->id,
            'rating' => 5,
            'body' => 'Perfect fit.',
            'is_approved' => false,
        ]);

        $this->assertContains('admin.review.submitted', $this->events($owner));
        $this->assertNotContains('review.approved', $this->events($customer));

        $this->actingAs($owner)->patch(route('admin.reviews.approve', $review))->assertRedirect();

        $this->assertContains('review.approved', $this->events($customer));
    }

    public function test_an_uploaded_prescription_is_queued_for_verification_and_the_result_comes_back(): void
    {
        $owner = $this->owner();
        $customer = User::factory()->create();

        $prescription = $customer->prescriptions()->create([
            'file_path' => 'prescriptions/scan.pdf',
            'pd' => 62.0,
        ]);

        $this->assertContains('admin.prescription.uploaded', $this->events($owner));

        $this->actingAs($owner)->patch(route('admin.prescriptions.verify', $prescription))->assertRedirect();

        $this->assertContains('prescription.verified', $this->events($customer));
    }

    public function test_a_prescription_typed_in_without_a_scan_needs_nobody_to_verify_it(): void
    {
        $owner = $this->owner();

        User::factory()->create()->prescriptions()->create(['pd' => 62.0]);

        $this->assertNotContains('admin.prescription.uploaded', $this->events($owner));
    }

    /*
    |--------------------------------------------------------------------------
    | Stock
    |--------------------------------------------------------------------------
    */

    public function test_stock_warns_the_owner_when_it_crosses_the_line_and_again_when_it_runs_out(): void
    {
        $owner = $this->owner();
        $frame = $this->frame(20);

        $frame->update(['stock' => Frame::LOW_STOCK_THRESHOLD - 1]);
        $this->assertContains('admin.stock.low', $this->events($owner));

        // Already below the line — one warning is enough.
        $frame->update(['stock' => 1]);
        $this->assertSame(1, $owner->notifications()->where('data->event', 'admin.stock.low')->count());

        $frame->update(['stock' => 0]);
        $this->assertContains('admin.stock.out', $this->events($owner));
    }

    public function test_restocking_is_not_an_alert(): void
    {
        $owner = $this->owner();
        $frame = $this->frame(1);

        $frame->update(['stock' => 50]);

        $this->assertSame([], $this->events($owner));
    }

    public function test_contact_lenses_use_their_own_higher_threshold(): void
    {
        $owner = $this->owner();

        $lens = ContactLens::create([
            'name' => 'DayFlow 30',
            'brand' => 'Lucent',
            'sku' => 'DF-'.uniqid(),
            'type' => 'daily',
            'material' => 'silicone_hydrogel',
            'pack_size' => 30,
            'price' => 24.00,
            'stock' => 40,
            'is_active' => true,
        ]);

        // Below a frame's threshold of 5 this would already have warned; a box
        // of lenses moves faster, so its line sits at 10.
        $lens->update(['stock' => 8]);

        $this->assertContains('admin.stock.low', $this->events($owner));
    }

    /*
    |--------------------------------------------------------------------------
    | Accounts
    |--------------------------------------------------------------------------
    */

    public function test_registering_welcomes_the_shopper_and_announces_them_to_the_owner(): void
    {
        $owner = $this->owner();

        $this->post('/register', [
            'first_name' => 'Nour',
            'last_name' => 'Haddad',
            'email' => 'nour@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $customer = User::where('email', 'nour@example.com')->sole();

        $this->assertContains('account.welcome', $this->events($customer));
        $this->assertContains('admin.customer.registered', $this->events($owner));
    }

    public function test_creating_a_staff_account_notifies_nobody(): void
    {
        $owner = $this->owner();

        User::factory()->admin()->create();

        $this->assertSame([], $this->events($owner));
    }

    /*
    |--------------------------------------------------------------------------
    | The inbox itself
    |--------------------------------------------------------------------------
    */

    public function test_the_inbox_lists_a_customers_own_notifications(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer);

        $this->actingAs($customer)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee($order->order_number);
    }

    public function test_staff_get_the_console_inbox_rather_than_the_storefront_one(): void
    {
        $owner = $this->owner();
        $this->order(User::factory()->create());

        $this->actingAs($owner)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Staff console');
    }

    public function test_opening_a_notification_marks_it_read_and_follows_its_link(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer);

        $notification = $customer->notifications()->where('data->event', 'order.placed')->sole();

        $this->actingAs($customer)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect(route('orders.show', $order, absolute: false));

        $this->assertNotNull($notification->refresh()->read_at);

        // The welcome message from signing up is untouched — opening one
        // notification reads that one only.
        $this->assertSame(['account.welcome'], $customer->unreadNotifications()->get()->pluck('data.event')->all());
    }

    public function test_marking_all_read_clears_the_bell(): void
    {
        $customer = User::factory()->create();
        $this->order($customer);

        $this->assertGreaterThan(0, $customer->unreadNotifications()->count());

        $this->actingAs($customer)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $customer->unreadNotifications()->count());
    }

    public function test_clearing_keeps_anything_still_unopened(): void
    {
        $customer = User::factory()->create();
        $this->order($customer);

        $read = $customer->notifications()->first();
        $read->markAsRead();

        $unreadBefore = $customer->unreadNotifications()->count();

        $this->actingAs($customer)->delete(route('notifications.clear'))->assertRedirect();

        $this->assertSame(0, $customer->readNotifications()->count());
        $this->assertSame($unreadBefore, $customer->unreadNotifications()->count());
    }

    public function test_one_customer_cannot_touch_another_customers_inbox(): void
    {
        $customer = User::factory()->create();
        $this->order($customer);

        $notification = $customer->notifications()->first();

        $this->actingAs(User::factory()->create())
            ->post(route('notifications.read', $notification->id))
            ->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->delete(route('notifications.destroy', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->refresh()->read_at);
    }

    public function test_the_inbox_is_closed_to_guests(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }
}
