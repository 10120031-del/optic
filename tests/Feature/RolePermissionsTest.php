<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_staff_can_enter_admin_console(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->staff()->create();

        $this->actingAs($owner)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_delivery_cannot_enter_admin_console(): void
    {
        $delivery = User::factory()->delivery()->create();

        $this->actingAs($delivery)->get(route('admin.dashboard'))->assertStatus(403);
        $this->actingAs($delivery)->get(route('admin.frames.index'))->assertStatus(403);
        $this->actingAs($delivery)->get(route('admin.promotions.index'))->assertStatus(403);
    }

    public function test_staff_cannot_manage_promotions(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get(route('admin.promotions.index'))->assertStatus(403);
        $this->actingAs($staff)->get(route('admin.promotions.create'))->assertStatus(403);
    }

    public function test_owner_can_manage_promotions(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->get(route('admin.promotions.index'))->assertOk();
        $this->actingAs($owner)->get(route('admin.promotions.create'))->assertOk();
    }

    public function test_staff_cannot_cancel_orders(): void
    {
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();
        $order = Order::create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-STAFF-1',
            'status' => 'pending',
            'subtotal' => 10,
            'shipping_cost' => 0,
            'tax' => 0,
            'total' => 10,
            'shipping_address_line' => '123 Main St',
            'shipping_city' => 'Paris',
            'shipping_postal_code' => '75000',
            'shipping_country' => 'France',
        ]);

        $this->actingAs($staff)
            ->patch(route('admin.orders.status', $order), ['status' => 'cancelled'])
            ->assertStatus(403);
    }

    public function test_delivery_user_only_sees_assigned_orders_and_can_update_them(): void
    {
        $delivery = User::factory()->delivery()->create();
        $customer = User::factory()->customer()->create();

        $assigned = Order::create([
            'user_id' => $customer->id,
            'assigned_delivery_user_id' => $delivery->id,
            'order_number' => 'ORD-DEL-1',
            'status' => 'processing',
            'subtotal' => 10,
            'shipping_cost' => 0,
            'tax' => 0,
            'total' => 10,
            'shipping_address_line' => '88 Market Street',
            'shipping_city' => 'London',
            'shipping_postal_code' => 'SW1A 1AA',
            'shipping_country' => 'United Kingdom',
        ]);

        $unassigned = Order::create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-DEL-2',
            'status' => 'pending',
            'subtotal' => 8,
            'shipping_cost' => 0,
            'tax' => 0,
            'total' => 8,
            'shipping_address_line' => '42 Palm Road',
            'shipping_city' => 'Berlin',
            'shipping_postal_code' => '10115',
            'shipping_country' => 'Germany',
        ]);

        $this->actingAs($delivery)
            ->get(route('delivery.orders.index'))
            ->assertOk()
            ->assertSee($assigned->order_number)
            ->assertDontSee($unassigned->order_number);

        $this->actingAs($delivery)
            ->patch(route('delivery.orders.status', $assigned), ['status' => 'shipped'])
            ->assertRedirect();

        $this->actingAs($delivery)
            ->patch(route('delivery.orders.status', $unassigned), ['status' => 'shipped'])
            ->assertStatus(403);
    }
}
