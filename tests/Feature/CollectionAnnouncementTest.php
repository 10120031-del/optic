<?php

namespace Tests\Feature;

use App\Mail\NewCollectionEmail;
use App\Models\Collection;
use App\Models\ContactLens;
use App\Models\Frame;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Announcing is the only action in the console that reaches every customer
 * at once, so these tests care as much about when it stays quiet as about
 * what it sends: assembling a collection must never notify anyone, and no
 * collection may be announced twice.
 */
class CollectionAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->admin()->create();
    }

    private function frame(): Frame
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
            'stock' => 20,
            'is_active' => true,
        ]);
    }

    private function lens(): ContactLens
    {
        return ContactLens::create([
            'name' => 'DayOne',
            'brand' => 'Lucent',
            'sku' => 'DAY-'.uniqid(),
            'type' => 'daily',
            'material' => 'hydrogel',
            'pack_size' => 30,
            'price' => 32.00,
            'stock' => 50,
            'is_active' => true,
        ]);
    }

    /** A saved-but-unannounced collection with one frame in it. */
    private function draft(User $owner): Collection
    {
        $collection = Collection::create([
            'name' => 'Autumn 25',
            'description' => 'Warm acetates for a cold month.',
            'created_by' => $owner->id,
        ]);

        $collection->frames()->attach($this->frame()->id, ['position' => 0]);

        return $collection;
    }

    /** @return array<int, string> The `event` key of each row in someone's inbox. */
    private function events(User $user): array
    {
        return $user->notifications()->get()->pluck('data.event')->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Building one is silent
    |--------------------------------------------------------------------------
    */

    public function test_creating_a_collection_notifies_nobody(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $customer = User::factory()->create();
        $frame = $this->frame();

        $this->actingAs($owner)->post(route('admin.collections.store'), [
            'name' => 'Autumn 25',
            'description' => 'Warm acetates for a cold month.',
            'frame_ids' => [$frame->id],
        ])->assertRedirect();

        $this->assertNotContains('collection.announced', $this->events($customer));
        Mail::assertNothingQueued();
        $this->assertNull(Collection::sole()->announced_at);
    }

    public function test_an_unannounced_collection_is_not_reachable_in_the_store(): void
    {
        $collection = $this->draft($this->owner());

        $this->get(route('collections.show', $collection))->assertNotFound();
        $this->get(route('collections.index'))->assertOk()->assertDontSee('Autumn 25');
    }

    /*
    |--------------------------------------------------------------------------
    | Announcing
    |--------------------------------------------------------------------------
    */

    public function test_announcing_reaches_every_customer_inbox_and_mails_the_subscribers(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $subscriber = User::factory()->create(['newsletter_opt_in' => true]);
        $unsubscribed = User::factory()->create(['newsletter_opt_in' => false]);

        $collection = $this->draft($owner);

        $this->actingAs($owner)
            ->post(route('admin.collections.announce', $collection))
            ->assertRedirect(route('admin.collections.index'));

        // The inbox row goes to every customer, opted in or not.
        $this->assertContains('collection.announced', $this->events($subscriber));
        $this->assertContains('collection.announced', $this->events($unsubscribed));

        // Marketing e-mail respects the opt-in.
        Mail::assertQueued(NewCollectionEmail::class, 1);
        Mail::assertQueued(
            NewCollectionEmail::class,
            fn (NewCollectionEmail $mail) => $mail->hasTo($subscriber->email),
        );

        // The owner gets a receipt, and the customers' count is recorded.
        $this->assertContains('admin.collection.announced', $this->events($owner));
        $this->assertSame(2, $collection->fresh()->recipients_count);
    }

    public function test_announcing_publishes_the_collection_to_the_storefront(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $collection = $this->draft($owner);
        $collection->contactLenses()->attach($this->lens()->id, ['position' => 0]);

        $this->actingAs($owner)->post(route('admin.collections.announce', $collection));

        $this->assertNotNull($collection->fresh()->announced_at);
        $this->get(route('collections.show', $collection))
            ->assertOk()
            ->assertSee('Autumn 25')
            ->assertSee('Meridian')
            ->assertSee('DayOne');
    }

    public function test_a_collection_cannot_be_announced_twice(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $customer = User::factory()->create(['newsletter_opt_in' => true]);
        $collection = $this->draft($owner);

        $this->actingAs($owner)->post(route('admin.collections.announce', $collection));
        $announcedAt = $collection->fresh()->announced_at;

        $this->actingAs($owner)->post(route('admin.collections.announce', $collection));

        Mail::assertQueued(NewCollectionEmail::class, 1);
        $this->assertCount(
            1,
            $customer->notifications()->where('data->event', 'collection.announced')->get()
        );
        $this->assertEquals($announcedAt, $collection->fresh()->announced_at);
    }

    public function test_an_empty_collection_cannot_be_announced(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $customer = User::factory()->create();

        $collection = Collection::create(['name' => 'Nothing Yet', 'created_by' => $owner->id]);

        $this->actingAs($owner)
            ->from(route('admin.collections.edit', $collection))
            ->post(route('admin.collections.announce', $collection))
            ->assertSessionHasErrors('announce');

        $this->assertNull($collection->fresh()->announced_at);
        $this->assertNotContains('collection.announced', $this->events($customer));
        Mail::assertNothingQueued();
    }

    public function test_editing_an_announced_collection_does_not_notify_again(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $customer = User::factory()->create(['newsletter_opt_in' => true]);
        $collection = $this->draft($owner);

        $this->actingAs($owner)->post(route('admin.collections.announce', $collection));

        $this->actingAs($owner)->put(route('admin.collections.update', $collection), [
            'name' => 'Autumn 25 — restocked',
            'frame_ids' => [$collection->frames()->sole()->id],
        ])->assertRedirect();

        $this->assertCount(
            1,
            $customer->notifications()->where('data->event', 'collection.announced')->get()
        );
        Mail::assertQueued(NewCollectionEmail::class, 1);
    }

    /*
    |--------------------------------------------------------------------------
    | The screens themselves render
    |--------------------------------------------------------------------------
    */

    public function test_the_console_screens_render_either_side_of_an_announcement(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $collection = $this->draft($owner);

        $this->actingAs($owner)->get(route('admin.collections.index'))
            ->assertOk()->assertSee('Autumn 25')->assertSee('Draft');
        $this->actingAs($owner)->get(route('admin.collections.create'))->assertOk();

        // Before: the announce button is offered.
        $this->actingAs($owner)->get(route('admin.collections.edit', $collection))
            ->assertOk()
            ->assertSee('Announce collection')
            ->assertSee('Meridian');

        $this->actingAs($owner)->post(route('admin.collections.announce', $collection));

        // After: it is gone, replaced by the record of what went out.
        $this->actingAs($owner)->get(route('admin.collections.edit', $collection))
            ->assertOk()
            ->assertDontSee('Announce collection')
            ->assertSee('Announced');

        $this->actingAs($owner)->get(route('admin.collections.index'))->assertOk()->assertSee('Live');
    }

    public function test_the_storefront_index_lists_announced_collections_only(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $announced = $this->draft($owner);
        $this->actingAs($owner)->post(route('admin.collections.announce', $announced));

        $hidden = Collection::create(['name' => 'Winter 26', 'created_by' => $owner->id]);
        $hidden->frames()->attach($this->frame()->id, ['position' => 0]);

        $this->get(route('collections.index'))
            ->assertOk()
            ->assertSee('Autumn 25')
            ->assertDontSee('Winter 26');
    }

    /*
    |--------------------------------------------------------------------------
    | Access
    |--------------------------------------------------------------------------
    */

    public function test_a_customer_cannot_announce_a_collection(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $collection = $this->draft($owner);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.collections.announce', $collection))
            ->assertForbidden();

        $this->assertNull($collection->fresh()->announced_at);
        Mail::assertNothingQueued();
    }

    public function test_the_slug_stays_put_when_the_name_changes(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $collection = $this->draft($owner);
        $slug = $collection->slug;

        $this->actingAs($owner)->put(route('admin.collections.update', $collection), [
            'name' => 'Something Else Entirely',
        ])->assertRedirect();

        $this->assertSame($slug, $collection->fresh()->slug);
    }
}
