<?php

namespace Tests\Feature;

use App\Models\Frame;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCustomerAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer']);
    }

    public static function customerRoutes(): array
    {
        return [
            'cart' => ['cart.index'],
            'orders' => ['orders.index'],
            'prescriptions' => ['prescriptions.index'],
            'new prescription' => ['prescriptions.create'],
        ];
    }

    /** @dataProvider customerRoutes */
    public function test_admin_is_redirected_off_customer_pages(string $route): void
    {
        $this->actingAs($this->admin())
            ->get(route($route))
            ->assertRedirect(route('admin.dashboard'));
    }

    /**
     * The customer gate must not fire for customers. Checkout is excluded from
     * the assertOk() set because CheckoutController aborts 422 on an empty
     * cart -- what matters there is that it is not a dashboard redirect.
     *
     * @dataProvider customerRoutes
     */
    public function test_customer_still_reaches_their_own_pages(string $route): void
    {
        $response = $this->actingAs($this->customer())->get(route($route));

        $response->assertOk();
    }

    public function test_customer_reaching_checkout_is_not_bounced_to_the_dashboard(): void
    {
        $this->actingAs($this->customer())
            ->get(route('checkout.index'))
            ->assertStatus(422); // empty cart, not an access redirect
    }

    public function test_admin_cannot_post_to_the_cart(): void
    {
        $this->actingAs($this->admin())
            ->post(route('cart.eyeglasses.store'), ['frame_id' => 1, 'lens_id' => 1])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_cannot_submit_a_review(): void
    {
        $this->actingAs($this->admin())
            ->post(route('reviews.store'), ['reviewable_type' => 'frame', 'reviewable_id' => 1, 'rating' => 5])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_guests_can_still_use_the_cart(): void
    {
        $this->get(route('cart.index'))->assertOk();
    }

    public function test_admin_can_still_browse_the_public_catalogue(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('home'))->assertOk();
        $this->actingAs($admin)->get(route('frames.index'))->assertOk();
        $this->actingAs($admin)->get(route('contact-lenses.index'))->assertOk();
    }

    public function test_browsing_the_storefront_creates_no_cart_for_an_admin(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('frames.index'))->assertOk();

        $this->assertDatabaseMissing('carts', ['user_id' => $admin->id]);
    }

    public function test_storefront_header_hides_customer_links_from_admin(): void
    {
        $res = $this->actingAs($this->admin())->get(route('home'));

        $res->assertOk();
        $res->assertDontSee(route('cart.index'));
        $res->assertDontSee(route('orders.index'));
        $res->assertDontSee(route('prescriptions.index'));
        $res->assertSee(route('admin.dashboard'));
    }

    public function test_admin_login_lands_on_the_dashboard_and_merges_no_cart(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'password']);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseMissing('carts', ['user_id' => $admin->id]);
    }

    public function test_customer_login_still_lands_on_the_storefront(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'password' => 'password']);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('home'));
    }

    private function frame(): Frame
    {
        return Frame::create([
            'name' => 'Aperture', 'brand' => 'Lucent', 'sku' => 'SKU-'.uniqid(),
            'lens_width' => 50, 'lens_height' => 40, 'bridge_width' => 18, 'temple_length' => 140,
            'material' => 'acetate', 'category' => 'eyeglasses', 'type' => 'full_rim',
            'gender' => 'unisex', 'price' => 120, 'stock' => 5, 'is_active' => true,
        ]);
    }

    public function test_product_page_shows_admin_a_staff_notice_instead_of_add_to_cart(): void
    {
        $res = $this->actingAs($this->admin())->get(route('frames.show', $this->frame()));

        $res->assertOk();
        $res->assertSee('Staff preview');
        $res->assertDontSee('Add to cart');
        $res->assertDontSee('Write a review');
    }

    public function test_product_page_still_offers_customers_add_to_cart_and_reviews(): void
    {
        $res = $this->actingAs($this->customer())->get(route('frames.show', $this->frame()));

        $res->assertOk();
        $res->assertSee('Add to cart');
        $res->assertSee('Write a review');
        $res->assertDontSee('Staff preview');
    }
}
