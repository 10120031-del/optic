<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_in_user_can_log_out(): void
    {
        $user = User::factory()->customer()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_stale_logout_request_does_not_show_session_timeout_page(): void
    {
        $user = User::factory()->customer()->create();

        $this->actingAs($user)
            ->withSession(['_token' => 'current-token'])
            ->post(route('logout'), ['_token' => 'stale-token'])
            ->assertRedirect(route('home'))
            ->assertStatus(302);

        $this->assertGuest();
    }

    public function test_logout_works_when_already_signed_out(): void
    {
        $this->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }
}
