<?php

namespace Database\Seeders\Demo;

use App\Models\Cart;
use App\Models\Collection as ProductCollection;
use App\Models\ContactLens;
use App\Models\ContactMessage;
use App\Models\Frame;
use App\Models\Lens;
use App\Models\Order;
use App\Models\PromotionCampaign;
use App\Models\User;
use App\Notifications\InboxNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Everything around the sale: what people looked at, what they left in a
 * cart, what they wrote in, what the shop sent out, and what is sitting in
 * everyone's inbox.
 *
 * These are the tables that make the rest look inhabited. Without product
 * views the dashboard's most-viewed panel and the whole view-to-purchase
 * comparison are empty; without carts the cart and checkout screens can only
 * be demonstrated by building one live; without notifications the bell in the
 * header never has a number on it.
 */
class DemoEngagementSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->seedProductViews();
        $this->seedOpenCarts();
        $this->seedCollections();
        $this->seedCampaigns();
        $this->seedContactMessages();
        $this->seedNotifications();
    }

    /*
    |--------------------------------------------------------------------------
    | Browsing
    |--------------------------------------------------------------------------
    */

    /**
     * Four thousand product views over the history window.
     *
     * Attention in a shop is not uniform — a handful of models get looked at
     * far more than the rest — so frames are given a weight before the draw
     * and the popular ones dominate. That is what makes the dashboard's
     * most-viewed list differ from its best-sellers list, which is the entire
     * point of tracking views separately from sales.
     *
     * Written through the query builder in chunks rather than one model per
     * row: four thousand Eloquent saves is most of a minute, and this table
     * has no observers or casts that a plain insert would bypass.
     */
    private function seedProductViews(): void
    {
        $frames = Frame::pluck('id')->all();
        $contactLenses = ContactLens::pluck('id')->all();

        if ($frames === [] && $contactLenses === []) {
            return;
        }

        $userIds = User::where('role', 'customer')
            ->where('email', 'like', '%@'.DemoConfig::EMAIL_DOMAIN)
            ->pluck('id')
            ->all();

        // Weight = how interesting this product is. Drawing the weight once
        // per product and reusing it is what makes the popularity stable
        // across the window instead of noise.
        $weights = [];

        foreach ($frames as $id) {
            $weights[] = [Frame::class, $id, DemoRandom::weighted([1 => 50, 4 => 30, 12 => 15, 30 => 5])];
        }

        foreach ($contactLenses as $id) {
            $weights[] = [ContactLens::class, $id, DemoRandom::weighted([1 => 55, 3 => 30, 8 => 15])];
        }

        $pool = [];

        foreach ($weights as [$type, $id, $weight]) {
            for ($i = 0; $i < $weight; $i++) {
                $pool[] = [$type, $id];
            }
        }

        $rows = [];

        for ($i = 0; $i < DemoConfig::PRODUCT_VIEWS; $i++) {
            [$type, $id] = DemoRandom::pick($pool);

            // Most browsing is done by people who are not signed in. Those
            // views still count towards the trends, which is why the column
            // is nullable and a session id stands in.
            $signedIn = $userIds !== [] && DemoRandom::chance(38);

            $rows[] = [
                'viewable_type' => $type,
                'viewable_id' => $id,
                'user_id' => $signedIn ? DemoRandom::pick($userIds) : null,
                'session_id' => $signedIn ? null : 'demo-'.DemoRandom::int(1000, 9999),
                'created_at' => DemoRandom::recentMoment(90)->toDateTimeString(),
            ];

            if (count($rows) >= 500) {
                DB::table('product_views')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('product_views')->insert($rows);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Carts left mid-shop
    |--------------------------------------------------------------------------
    */

    /**
     * Carts belonging to signed-in customers who have not checked out.
     *
     * Sign in as any of these accounts and the cart page has something in it
     * and the checkout is one click away — which is the difference between
     * demonstrating checkout and building a basket on stage first.
     *
     * The busiest customers are given carts first, deliberately. They are the
     * accounts with an order history worth opening, and DemoSeeder names the
     * busiest of them in the credentials it prints — so the one account a
     * presenter is most likely to sign in as can demonstrate order history,
     * prescriptions and checkout without switching users.
     */
    private function seedOpenCarts(): void
    {
        $frames = Frame::where('is_active', true)->where('stock', '>', 0)->get();
        $lenses = Lens::where('is_active', true)->get();
        $contactLenses = ContactLens::where('is_active', true)->where('stock', '>', 0)->get();

        if ($frames->isEmpty() || $lenses->isEmpty()) {
            return;
        }

        $customers = User::where('role', 'customer')
            ->where('email', 'like', '%@'.DemoConfig::EMAIL_DOMAIN)
            ->with('prescriptions')
            ->withCount('orders')
            ->orderByDesc('orders_count')
            ->orderBy('id')
            ->get();

        if ($customers->isEmpty()) {
            return;
        }

        // The three busiest get one for certain; the rest are drawn at random
        // so the carts are not all concentrated on the same few accounts.
        $chosen = $customers->take(3);
        $chosen = $chosen->merge(
            DemoRandom::pickMany(
                $customers->slice(3)->all(),
                max(0, min(DemoConfig::OPEN_CARTS, $customers->count()) - $chosen->count())
            )
        );

        foreach ($chosen as $customer) {
            $cart = Cart::updateOrCreate(['user_id' => $customer->id], []);

            // Rebuilt from scratch each run: a cart is a working document,
            // not history, and appending to one across runs would quietly
            // grow it every time the seeder is used.
            $cart->eyeglasses()->delete();
            $cart->contactLenses()->delete();

            $touchedAt = DemoRandom::recentMoment(12);

            // Decide the shape of the basket up front so an empty one is
            // impossible. A cart row with no lines in it is indistinguishable
            // from no cart at all on screen, and would waste one of the few
            // accounts set aside for demonstrating checkout.
            $wantsContacts = $contactLenses->isNotEmpty() && DemoRandom::chance(45);
            $wantsEyeglasses = DemoRandom::chance(75) || ! $wantsContacts;

            if ($wantsEyeglasses) {
                $frame = DemoRandom::pick($frames->all());
                $lens = DemoRandom::pick($lenses->where('type', '!=', 'plano')->all() ?: $lenses->all());
                $prescription = $customer->prescriptions->first(fn ($p) => ! $p->isExpired());

                $line = $cart->eyeglasses()->create([
                    'frame_id' => $frame->id,
                    'lens_id' => $lens->id,
                    'prescription_id' => $prescription?->id,
                    'quantity' => 1,
                    'right_sphere' => $prescription?->right_sphere ?? DemoRandom::quarterStep(-4.00, 1.00),
                    'right_cylinder' => $prescription?->right_cylinder,
                    'right_axis' => $prescription?->right_axis,
                    'right_add' => $prescription?->right_add,
                    'left_sphere' => $prescription?->left_sphere ?? DemoRandom::quarterStep(-4.00, 1.00),
                    'left_cylinder' => $prescription?->left_cylinder,
                    'left_axis' => $prescription?->left_axis,
                    'left_add' => $prescription?->left_add,
                    'pd' => $prescription?->pd ?? DemoRandom::float(58.0, 68.0, 1),
                ]);

                // Cart lines carry features through a pivot, not through
                // snapshot rows the way order lines do — the price is still
                // whatever the catalogue says until checkout freezes it.
                $features = $lens->features;

                if ($features->isNotEmpty()) {
                    $chosen = DemoRandom::pickMany($features->all(), DemoRandom::int(1, min(2, $features->count())));
                    $line->features()->attach(array_map(fn ($feature) => $feature->id, $chosen));
                }

                $line->forceFill(['created_at' => $touchedAt, 'updated_at' => $touchedAt])->save();
            }

            if ($wantsContacts) {
                $product = DemoRandom::pick($contactLenses->all());

                $line = $cart->contactLenses()->create([
                    'contact_lens_id' => $product->id,
                    'quantity' => DemoRandom::int(1, 2),
                    'right_power' => DemoRandom::quarterStep(-5.00, -0.75),
                    'left_power' => DemoRandom::quarterStep(-5.00, -0.75),
                ]);

                $line->forceFill(['created_at' => $touchedAt, 'updated_at' => $touchedAt])->save();
            }

            $cart->forceFill(['updated_at' => $touchedAt])->save();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Curated drops
    |--------------------------------------------------------------------------
    */

    /**
     * Four curated collections, three announced and one still being put
     * together in the console.
     *
     * The unannounced one is the interesting row: the storefront's Published
     * scope hides it, so it demonstrates that announcing is a deliberate act
     * and not just a save.
     */
    private function seedCollections(): void
    {
        $owner = User::where('email', 'owner@'.DemoConfig::EMAIL_DOMAIN)->first();

        $definitions = [
            [
                'name' => 'Autumn 26',
                'description' => 'Warm acetates and tortoise finishes for the turn of the season — the frames we reach for once the light goes soft.',
                'announced' => 34,
                'frames' => ['FR-1001', 'FR-1008', 'FR-1024', 'FR-1032', 'FR-1038', 'FR-1009'],
                'lenses' => [],
            ],
            [
                'name' => 'Titanium Series',
                'description' => 'Our lightest frames, all under twenty grams. Titanium fronts, flexible temples, and nothing on your face you have to think about.',
                'announced' => 71,
                'frames' => ['FR-1005', 'FR-1011', 'FR-1015', 'FR-1023', 'FR-1031', 'FR-1040'],
                'lenses' => [],
            ],
            [
                'name' => 'Sun & Sea',
                'description' => 'Polarised, mirrored and oversized — everything for a summer on the coast, plus the contact lenses to pack alongside them.',
                'announced' => 12,
                'frames' => ['FR-1002', 'FR-1017', 'FR-1018', 'FR-1019', 'FR-1027', 'FR-1033', 'FR-1039'],
                'lenses' => ['CL-2005', 'CL-2006'],
            ],
            [
                'name' => 'Back to School',
                'description' => 'Kids’ frames built to survive a playground, and the impact-resistant lenses to go in them.',
                'announced' => null,
                'frames' => ['FR-1007', 'FR-1028', 'FR-1029', 'FR-1030'],
                'lenses' => [],
            ],
        ];

        foreach ($definitions as $definition) {
            $collection = ProductCollection::updateOrCreate(
                ['slug' => Str::slug($definition['name'])],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_active' => true,
                    'created_by' => $owner?->id,
                ]
            );

            $frameIds = Frame::whereIn('sku', $definition['frames'])->pluck('id')->all();
            $lensIds = ContactLens::whereIn('sku', $definition['lenses'])->pluck('id')->all();

            $collection->frames()->sync($this->positioned($frameIds));
            $collection->contactLenses()->sync($this->positioned($lensIds, count($frameIds)));

            $announcedAt = $definition['announced'] === null
                ? null
                : CarbonImmutable::now()->subDays($definition['announced'])->setTime(10, 0);

            $collection->forceFill([
                'announced_at' => $announcedAt,
                // Null until announced, which is what the column comments say
                // it means — a count of zero would read as "we told nobody".
                'recipients_count' => $announcedAt === null ? null : DemoRandom::int(28, 44),
                'created_at' => $announcedAt?->subDays(DemoRandom::int(3, 12)) ?? CarbonImmutable::now()->subDays(4),
            ])->save();
        }
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array<string, int>>
     */
    private function positioned(array $ids, int $offset = 0): array
    {
        $synced = [];

        foreach ($ids as $i => $id) {
            $synced[$id] = ['position' => $offset + $i];
        }

        return $synced;
    }

    /*
    |--------------------------------------------------------------------------
    | Outbound and inbound mail
    |--------------------------------------------------------------------------
    */

    private function seedCampaigns(): void
    {
        $owner = User::where('email', 'owner@'.DemoConfig::EMAIL_DOMAIN)->first();
        $subscribers = User::where('role', 'customer')->where('newsletter_opt_in', true)->count();

        $campaigns = [
            ['title' => 'Autumn 26 launch', 'subject' => 'Autumn 26 is here — warm acetates, softer light', 'audience' => 'newsletter_subscribers', 'sent' => 34, 'body' => "Our autumn drop is live.\n\nSix frames in warm acetate and tortoise, cut a little deeper than the summer range so they sit well under a scarf. Every pair comes with our standard anti-reflective coating included this month.\n\nBrowse the collection on the site — and reply to this email if you would like us to hold a pair to try on."],
            ['title' => 'Free anti-blue-light week', 'subject' => 'This week only: anti-blue-light on us', 'audience' => 'newsletter_subscribers', 'sent' => 58, 'body' => "If you spend your day in front of a screen, this one is for you.\n\nFor the next seven days we are adding our anti-blue-light coating to any prescription order at no charge. Nothing to enter at checkout — pick the coating and the price comes off.\n\nOffer runs to Sunday midnight."],
            ['title' => 'Prescription check reminder', 'subject' => 'When did you last have your eyes tested?', 'audience' => 'customers', 'sent' => 89, 'body' => "A quick reminder rather than a sale.\n\nMost prescriptions are written for two years, and a fair few of the ones on file with us are approaching that. If yours has expired we cannot cut lenses against it — so it is worth booking a test before you next order.\n\nYou can see the expiry date on any prescription saved to your account."],
            ['title' => 'Titanium Series announcement', 'subject' => 'Frames you stop noticing: the Titanium Series', 'audience' => 'newsletter_subscribers', 'sent' => 71, 'body' => "Six frames, none of them heavier than twenty grams.\n\nTitanium fronts, flexible temples, and rimless or semi-rimless throughout. If you have ever taken your glasses off at lunch because of the weight, start here."],
            ['title' => 'Summer sunglasses', 'subject' => 'Polarised, mirrored, and ready for the coast', 'audience' => 'all', 'sent' => 12, 'body' => "Sun & Sea is live.\n\nSeven sunglass frames — polarised as standard on the sports models — plus the daily contact lenses worth packing alongside them.\n\nFree delivery across Lebanon on any order over $150 this month."],
        ];

        foreach ($campaigns as $campaign) {
            $sentAt = CarbonImmutable::now()->subDays($campaign['sent'])->setTime(9, DemoRandom::int(0, 45));

            $record = PromotionCampaign::updateOrCreate(
                ['title' => $campaign['title']],
                [
                    'subject' => $campaign['subject'],
                    'body' => $campaign['body'],
                    'audience' => $campaign['audience'],
                    'created_by' => $owner?->id,
                    'recipients_count' => $campaign['audience'] === 'newsletter_subscribers'
                        ? $subscribers
                        : User::where('role', 'customer')->count(),
                    'sent_at' => $sentAt,
                ]
            );

            $record->forceFill([
                'created_at' => $sentAt->subDays(DemoRandom::int(1, 5)),
                'updated_at' => $sentAt,
            ])->save();
        }
    }

    /**
     * Enquiries from the About page's contact form.
     *
     * Half are from signed-in customers and half from visitors — the form is
     * open to anyone, which is the reason user_id is nullable — and the
     * statuses are spread so the console's unhandled badge has a number on it
     * without the list being nothing but unread rows.
     */
    private function seedContactMessages(): void
    {
        $customers = User::where('role', 'customer')
            ->where('email', 'like', '%@'.DemoConfig::EMAIL_DOMAIN)
            ->get();

        $handlers = User::whereIn('role', ['owner', 'staff'])
            ->where('email', 'like', '%@'.DemoConfig::EMAIL_DOMAIN)
            ->pluck('id')
            ->all();

        $messages = [
            ['topic' => 'order', 'message' => 'I placed an order four days ago and the tracking has not moved since Tuesday. Could someone check where it is?'],
            ['topic' => 'prescription', 'message' => 'My optometrist wrote my prescription with the cylinder in plus notation. Do I convert it before entering it, or do you handle that?'],
            ['topic' => 'returns', 'message' => 'The frame I received is too wide. What is the process for exchanging it for the next size down?'],
            ['topic' => 'general', 'message' => 'Do you do repairs? One of the temples on a pair I bought from you last year has come loose at the hinge.'],
            ['topic' => 'wholesale', 'message' => 'I run a small optical shop in Zahle and I am interested in stocking the Cedar & Co range. Who should I speak to about trade pricing?'],
            ['topic' => 'general', 'message' => 'Is the face-match tool storing my photo anywhere? I would rather it did not.'],
            ['topic' => 'prescription', 'message' => 'Can you cut progressive lenses into the Meridian Air? The listing does not say either way and it is rimless.'],
            ['topic' => 'order', 'message' => 'I need to change the delivery address on an order I placed this morning — I put my old one in by mistake.'],
            ['topic' => 'general', 'message' => 'Do you have a physical shop I can visit to try frames on before ordering?'],
            ['topic' => 'returns', 'message' => 'I sent a return back a week ago with the label you emailed. Has it arrived?'],
            ['topic' => 'other', 'message' => 'Your site works beautifully on my phone. Just wanted to say so — most optical sites do not.'],
            ['topic' => 'prescription', 'message' => 'My PD is not written on my prescription. Is there a way to measure it at home accurately enough?'],
            ['topic' => 'order', 'message' => 'Can I add a second pair to an order that has not shipped yet, or should I place a new one?'],
            ['topic' => 'wholesale', 'message' => 'Do you offer corporate rates? We would like to arrange safety eyewear for about forty staff.'],
            ['topic' => 'general', 'message' => 'Are the sunglasses in the Sun & Sea collection all polarised, or only the sports ones?'],
            ['topic' => 'returns', 'message' => 'The lenses arrived with the wrong axis on the right eye. I have attached my prescription for comparison.'],
            ['topic' => 'other', 'message' => 'Do you ship outside Lebanon? I am in Dubai and would like to order two pairs.'],
            ['topic' => 'general', 'message' => 'How long do contact lens orders usually take to arrive? I am nearly out.'],
        ];

        $guestNames = [
            ['Rania Chalhoub', 'rania.chalhoub@'.DemoConfig::EMAIL_DOMAIN],
            ['Tarek Osseiran', 'tarek.osseiran@'.DemoConfig::EMAIL_DOMAIN],
            ['Grace Abi Nader', 'grace.abinader@'.DemoConfig::EMAIL_DOMAIN],
            ['Wassim Barakat', 'wassim.barakat@'.DemoConfig::EMAIL_DOMAIN],
            ['Nisrine Kanaan', 'nisrine.kanaan@'.DemoConfig::EMAIL_DOMAIN],
        ];

        foreach (array_slice($messages, 0, DemoConfig::CONTACT_MESSAGES) as $i => $message) {
            $sender = $customers->isNotEmpty() && DemoRandom::chance(55) ? DemoRandom::pick($customers->all()) : null;

            if ($sender !== null) {
                $name = trim($sender->first_name.' '.$sender->last_name);
                $email = $sender->email;
                $phone = $sender->phone_number;
            } else {
                [$name, $email] = $guestNames[$i % count($guestNames)];
                $phone = DemoRandom::chance(60) ? sprintf('+961 %d %d %d', DemoRandom::int(3, 8), DemoRandom::int(100, 999), DemoRandom::int(100, 999)) : null;
            }

            $status = (string) DemoRandom::weighted([
                ContactMessage::STATUS_NEW => 35,
                ContactMessage::STATUS_READ => 30,
                ContactMessage::STATUS_CLOSED => 35,
            ]);

            $sentAt = DemoRandom::recentMoment(60);

            $record = ContactMessage::create([
                'user_id' => $sender?->id,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'topic' => $message['topic'],
                'message' => $message['message'],
                'status' => $status,
            ]);

            $handled = $status !== ContactMessage::STATUS_NEW && $handlers !== [];

            $record->forceFill([
                'handled_by' => $handled ? DemoRandom::pick($handlers) : null,
                'handled_at' => $handled ? $sentAt->addHours(DemoRandom::int(1, 48))->min(CarbonImmutable::now()) : null,
                'created_at' => $sentAt,
                'updated_at' => $sentAt,
            ])->save();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Inboxes
    |--------------------------------------------------------------------------
    */

    /**
     * Fill the header bell and the console's Inbox tab.
     *
     * Normally these rows are written by App\Services\Notifier from the model
     * observers — but the seeder runs with model events muted (firing them
     * would mean a hundred and forty "new order" notifications landing in one
     * burst, dated today, for orders that are months old). So the rows are
     * written directly here, in the same shape Notifier produces: a stable
     * event key, the title and body the inbox renders, a relative url, and a
     * level that picks the badge colour.
     *
     * Only the most recent slice of orders is covered. An inbox with five
     * hundred rows in it is not a demonstration of an inbox.
     */
    private function seedNotifications(): void
    {
        $owner = User::where('email', 'owner@'.DemoConfig::EMAIL_DOMAIN)->first();

        $orders = Order::whereHas('user', fn ($q) => $q->where('email', 'like', '%@'.DemoConfig::EMAIL_DOMAIN))
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $rows = [];

        foreach ($orders as $order) {
            $total = '$'.number_format((float) $order->total, 2);

            // What the customer heard when they placed it.
            $rows[] = $this->notification(
                $order->user_id,
                'order.placed',
                "Order {$order->order_number} confirmed",
                "We have your order for {$total}. Payment is cash on delivery — we will let you know as soon as it ships.",
                "/orders/{$order->id}",
                'success',
                $order->created_at->toImmutable(),
                readChance: 85,
            );

            // And what they heard when it moved on.
            if ($order->status !== 'pending') {
                [$title, $body, $level] = $this->statusCopy($order);

                $rows[] = $this->notification(
                    $order->user_id,
                    'order.status',
                    $title,
                    $body,
                    "/orders/{$order->id}",
                    $level,
                    ($order->delivered_at ?? $order->shipped_at ?? $order->cancelled_at ?? $order->paid_at ?? $order->created_at)->toImmutable(),
                    readChance: 70,
                );
            }

            // And what the shop heard.
            if ($owner !== null) {
                $rows[] = $this->notification(
                    $owner->id,
                    'admin.order.placed',
                    "New order {$order->order_number} — {$total}",
                    sprintf('Placed by %s %s, shipping to %s.', $order->user->first_name, $order->user->last_name, $order->shipping_city),
                    "/admin/orders/{$order->id}",
                    'success',
                    $order->created_at->toImmutable(),
                    readChance: 45,
                );
            }
        }

        // The queues the owner has not cleared yet, so the bell has a count
        // on it the moment they sign in.
        if ($owner !== null) {
            foreach (Frame::where('is_active', true)->where('stock', '<=', Frame::LOW_STOCK_THRESHOLD)->get() as $frame) {
                $rows[] = $this->notification(
                    $owner->id,
                    $frame->stock === 0 ? 'stock.out' : 'stock.low',
                    $frame->stock === 0
                        ? "{$frame->name} is out of stock"
                        : "{$frame->name} is down to {$frame->stock} left",
                    "SKU {$frame->sku}. Reorder before it drops off the storefront.",
                    "/admin/frames/{$frame->id}/edit",
                    $frame->stock === 0 ? 'danger' : 'warn',
                    CarbonImmutable::now()->subDays(DemoRandom::int(1, 12)),
                    readChance: 20,
                );
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('notifications')->insert($chunk);
        }
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function statusCopy(Order $order): array
    {
        return match ($order->status) {
            'paid' => ["Payment recorded for order {$order->order_number}", 'We have marked your payment as received. Thank you!', 'success'],
            'processing' => ["Order {$order->order_number} is being prepared", 'Your lenses are being cut and fitted. We will tell you the moment it leaves us.', 'info'],
            'shipped' => ["Order {$order->order_number} is on its way", trim("On its way with {$order->carrier}. Tracking: {$order->tracking_number}."), 'info'],
            'delivered' => ["Order {$order->order_number} was delivered", 'Enjoy your new eyewear — and do leave a review, it helps other shoppers.', 'success'],
            'cancelled' => ["Order {$order->order_number} was cancelled", 'Nothing was charged. Get in touch if this was not what you expected.', 'danger'],
            'refunded' => ["Order {$order->order_number} was refunded", 'The refund has been recorded against your order.', 'warn'],
            default => ["Order {$order->order_number} was updated", '', 'info'],
        };
    }

    /**
     * One notifications row, in Laravel's own database-channel shape.
     *
     * @return array<string, mixed>
     */
    private function notification(
        int $userId,
        string $event,
        string $title,
        string $body,
        string $url,
        string $level,
        CarbonImmutable $at,
        int $readChance,
    ): array {
        $at = $at->min(CarbonImmutable::now());

        return [
            'id' => (string) Str::uuid(),
            'type' => InboxNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $userId,
            'data' => json_encode([
                'event' => $event,
                'title' => $title,
                'body' => $body === '' ? null : $body,
                'url' => $url,
                'level' => $level,
            ]),
            'read_at' => DemoRandom::chance($readChance)
                ? $at->addHours(DemoRandom::int(1, 60))->min(CarbonImmutable::now())->toDateTimeString()
                : null,
            'created_at' => $at->toDateTimeString(),
            'updated_at' => $at->toDateTimeString(),
        ];
    }
}
