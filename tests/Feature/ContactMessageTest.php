<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The About page's contact form, end to end: a visitor sends an enquiry, it
 * lands in the table, the shop hears about it in the staff inbox, and only
 * staff can read the queue afterwards.
 */
class ContactMessageTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nadia Haddad',
            'email' => 'nadia@example.com',
            'phone' => '+961 71 000 111',
            'topic' => 'order',
            'message' => 'Could you tell me whether the Meridian frame comes in a 52mm lens width?',
        ], $overrides);
    }

    public function test_about_page_is_public(): void
    {
        $this->get('/about')
            ->assertOk()
            ->assertSee('Contact us')
            ->assertSee(config('contact.email'));
    }

    public function test_a_guest_can_send_a_message_and_staff_are_notified(): void
    {
        $owner = User::factory()->owner()->create();

        $this->post('/contact', $this->payload())
            ->assertRedirect(route('about').'#contact')
            ->assertSessionHas('contact_status');

        $message = ContactMessage::sole();
        $this->assertSame('nadia@example.com', $message->email);
        $this->assertSame('order', $message->topic);
        $this->assertSame(ContactMessage::STATUS_NEW, $message->status);
        // A visitor has no account, so nothing should be attached to one.
        $this->assertNull($message->user_id);

        $this->assertSame(1, $owner->unreadNotifications()->count());
        $this->assertSame(
            'admin.contact.received',
            $owner->notifications()->first()->data['event'],
        );
    }

    public function test_a_signed_in_customer_is_linked_to_their_message(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)->post('/contact', $this->payload())->assertRedirect();

        $this->assertSame($customer->id, ContactMessage::sole()->user_id);
    }

    public function test_a_message_needs_an_email_a_topic_and_some_words(): void
    {
        $this->post('/contact', $this->payload(['email' => 'not-an-email']))
            ->assertSessionHasErrors('email');

        $this->post('/contact', $this->payload(['message' => 'hi']))
            ->assertSessionHasErrors('message');

        $this->post('/contact', $this->payload(['topic' => 'refunds-please']))
            ->assertSessionHasErrors('topic');

        $this->assertSame(0, ContactMessage::count());
    }

    /**
     * The honeypot answers exactly as a real submission does — a bot tuning
     * against the response learns nothing — but stores nothing.
     */
    public function test_a_filled_honeypot_is_accepted_and_discarded(): void
    {
        $this->post('/contact', $this->payload(['website' => 'https://spam.example']))
            ->assertSessionHas('contact_status')
            ->assertSessionHasNoErrors();

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_only_staff_can_read_the_message_queue(): void
    {
        ContactMessage::create($this->payload() + ['status' => ContactMessage::STATUS_NEW]);

        $this->get('/admin/messages')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->customer()->create())
            ->get('/admin/messages')
            ->assertForbidden();

        $this->actingAs(User::factory()->staff()->create())
            ->get('/admin/messages')
            ->assertOk()
            ->assertSee('Nadia Haddad');
    }

    public function test_staff_can_mark_a_message_handled_and_delete_it(): void
    {
        $staff = User::factory()->staff()->create();
        $message = ContactMessage::create($this->payload() + ['status' => ContactMessage::STATUS_NEW]);

        $this->actingAs($staff)
            ->patch("/admin/messages/{$message->id}/status", ['status' => ContactMessage::STATUS_CLOSED])
            ->assertRedirect();

        $message->refresh();
        $this->assertSame(ContactMessage::STATUS_CLOSED, $message->status);
        $this->assertSame($staff->id, $message->handled_by);
        $this->assertNotNull($message->handled_at);

        $this->actingAs($staff)->delete("/admin/messages/{$message->id}")->assertRedirect();
        $this->assertSame(0, ContactMessage::count());
    }
}
