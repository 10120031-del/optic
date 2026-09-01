<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The link the shop actually mails, rebuilt here so the tests exercise the
     * same signed route rather than a hand-rolled path.
     */
    private function verificationUrl(User $user, ?string $hashSource = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->getKey(),
            'hash' => sha1($hashSource ?? $user->getEmailForVerification()),
        ]);
    }

    public function test_registering_sends_a_confirmation_link(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'first_name' => 'Nadia',
            'last_name' => 'Haddad',
            'email' => 'nadia@example.com',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'nadia@example.com')->firstOrFail();

        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_notice_page_is_shown_to_an_unverified_user(): void
    {
        $user = User::factory()->customer()->unverified()->create();

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertSee($user->email);
    }

    public function test_notice_page_sends_a_verified_user_away(): void
    {
        $user = User::factory()->customer()->create();

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertRedirect(route('home'));
    }

    public function test_guests_cannot_reach_the_verification_pages(): void
    {
        $this->get(route('verification.notice'))->assertRedirect(route('login'));
    }

    public function test_signed_link_verifies_the_address(): void
    {
        Event::fake();

        $user = User::factory()->customer()->unverified()->create();

        $this->actingAs($user)
            ->get($this->verificationUrl($user))
            ->assertRedirect(route('home'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class);
    }

    public function test_an_unsigned_link_verifies_nothing(): void
    {
        $user = User::factory()->customer()->unverified()->create();

        $this->actingAs($user)
            ->get(route('verification.verify', [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]))
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_link_whose_hash_does_not_match_the_address_verifies_nothing(): void
    {
        $user = User::factory()->customer()->unverified()->create();

        $this->actingAs($user)
            ->get($this->verificationUrl($user, 'some-other@example.com'))
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_one_user_cannot_verify_another_users_address(): void
    {
        $target = User::factory()->customer()->unverified()->create();
        $attacker = User::factory()->customer()->create();

        $this->actingAs($attacker)
            ->get($this->verificationUrl($target))
            ->assertForbidden();

        $this->assertFalse($target->fresh()->hasVerifiedEmail());
    }

    public function test_clicking_the_link_a_second_time_is_harmless(): void
    {
        $user = User::factory()->customer()->create();

        $this->actingAs($user)
            ->get($this->verificationUrl($user))
            ->assertRedirect(route('home'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_verifying_ignores_a_stale_intended_url(): void
    {
        // Bumping into a login wall on the way in leaves an intended URL in
        // the session that survives registration. Following it after
        // confirming would drop a customer onto a 403 page, so verification
        // always lands on home instead.
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        $user = User::factory()->customer()->unverified()->create();

        $this->actingAs($user)
            ->get($this->verificationUrl($user))
            ->assertRedirect(route('home'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_an_unverified_user_can_ask_for_a_fresh_link(): void
    {
        Notification::fake();

        $user = User::factory()->customer()->unverified()->create();

        $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('status');

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_a_verified_user_asking_again_sends_no_mail(): void
    {
        Notification::fake();

        $user = User::factory()->customer()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect(route('home'));

        // Scoped to this notification rather than assertNothingSent(): creating
        // a customer also drops an InboxNotification on the owner.
        Notification::assertNotSentTo($user, VerifyEmailNotification::class);
    }

    public function test_unverified_users_are_reminded_while_they_browse(): void
    {
        $user = User::factory()->customer()->unverified()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Confirm your email address so we can send you order and delivery updates.');
    }

    public function test_verified_users_are_not_reminded(): void
    {
        $user = User::factory()->customer()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('Confirm your email address so we can send you order and delivery updates.');
    }
}
