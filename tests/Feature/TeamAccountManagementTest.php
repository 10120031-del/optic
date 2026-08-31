<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_list_and_promote_a_customer_to_staff(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->customer()->create();

        $this->actingAs($owner)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee($customer->email);

        $this->actingAs($owner)
            ->patch(route('admin.users.role', $customer), ['role' => 'staff'])
            ->assertRedirect();

        $this->assertSame('staff', $customer->fresh()->role);
    }

    public function test_owner_can_promote_a_customer_to_delivery(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->customer()->create();

        $this->actingAs($owner)
            ->patch(route('admin.users.role', $customer), ['role' => 'delivery'])
            ->assertRedirect();

        $this->assertSame('delivery', $customer->fresh()->role);
    }

    public function test_owner_can_demote_staff_back_to_customer(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->staff()->create();

        $this->actingAs($owner)
            ->patch(route('admin.users.role', $staff), ['role' => 'customer'])
            ->assertRedirect();

        $this->assertSame('customer', $staff->fresh()->role);
    }

    public function test_staff_cannot_manage_team_accounts(): void
    {
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();

        $this->actingAs($staff)->get(route('admin.users.index'))->assertStatus(403);
        $this->actingAs($staff)
            ->patch(route('admin.users.role', $customer), ['role' => 'delivery'])
            ->assertStatus(403);
    }

    public function test_owner_cannot_change_another_owner_account(): void
    {
        $owner = User::factory()->owner()->create();
        $otherOwner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->get(route('admin.users.show', $otherOwner))
            ->assertStatus(404);

        $this->actingAs($owner)
            ->patch(route('admin.users.role', $otherOwner), ['role' => 'staff'])
            ->assertStatus(403);
    }

    public function test_owner_cannot_change_their_own_role(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->patch(route('admin.users.role', $owner), ['role' => 'staff'])
            ->assertStatus(403);
    }
}
