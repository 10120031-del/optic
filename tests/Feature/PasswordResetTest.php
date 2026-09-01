<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_is_reachable_from_login(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('password.request'), escape: false);

        $this->get(route('password.request'))->assertOk();
    }

    public function test_requesting_a_link_emails_the_account_holder(): void
    {
        Notification::fake();

        $user = User::factory()->customer()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_unknown_address_gets_the_same_answer_as_a_known_one(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_emailed_link_opens_the_reset_form(): void
    {
        Notification::fake();

        $user = User::factory()->customer()->create();
        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
            $this->get(route('password.reset', ['token' => $notification->token, 'email' => $user->email]))
                ->assertOk()
                ->assertSee($user->email);

            return true;
        });
    }

    public function test_valid_token_sets_the_new_password(): void
    {
        Notification::fake();

        $user = User::factory()->customer()->create();
        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
            $this->post(route('password.store'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'a-brand-new-password',
                'password_confirmation' => 'a-brand-new-password',
            ])->assertRedirect(route('login'))->assertSessionHasNoErrors();

            return true;
        });

        $this->assertTrue(Hash::check('a-brand-new-password', $user->fresh()->password));
    }

    public function test_the_new_password_signs_the_user_in(): void
    {
        Notification::fake();

        $user = User::factory()->customer()->create();
        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
            $this->post(route('password.store'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'a-brand-new-password',
                'password_confirmation' => 'a-brand-new-password',
            ]);

            return true;
        });

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'a-brand-new-password',
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_a_token_cannot_be_spent_twice(): void
    {
        Notification::fake();

        $user = User::factory()->customer()->create();
        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
            $payload = [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'a-brand-new-password',
                'password_confirmation' => 'a-brand-new-password',
            ];

            $this->post(route('password.store'), $payload)->assertSessionHasNoErrors();

            $this->post(route('password.store'), array_merge($payload, [
                'password' => 'second-attempt-password',
                'password_confirmation' => 'second-attempt-password',
            ]))->assertSessionHasErrors('email');

            return true;
        });

        $this->assertTrue(Hash::check('a-brand-new-password', $user->fresh()->password));
    }

    public function test_a_forged_token_is_rejected(): void
    {
        $user = User::factory()->customer()->create();

        $this->post(route('password.store'), [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertSessionHasErrors('email');

        $this->assertFalse(Hash::check('a-brand-new-password', $user->fresh()->password));
    }

    public function test_new_password_must_be_confirmed_and_long_enough(): void
    {
        $this->post(route('password.store'), [
            'token' => 'whatever',
            'email' => 'someone@example.com',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ])->assertSessionHasErrors('password');
    }

    public function test_signed_in_users_are_kept_off_the_reset_screens(): void
    {
        $user = User::factory()->customer()->create();

        $this->actingAs($user)
            ->get(route('password.request'))
            ->assertRedirect(route('home'));
    }
}
