<?php

namespace Database\Seeders\Demo;

use App\Models\ContactLens;
use App\Models\Frame;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Product reviews, written mostly by people who actually bought the thing.
 *
 * The verified-purchase badge is the whole reason the reviews table carries
 * an order_id, so most of these are built backwards from real delivered
 * orders: take a line, and let the customer who received it review the
 * product it was for. The remainder are unbadged reviews from other
 * customers, because a storefront where every single review is verified does
 * not demonstrate the badge either.
 *
 * A sixth are left unapproved. Those are not noise — they are the moderation
 * queue the staff console exists to work through, and the count the dashboard
 * badges.
 *
 * The unique index on (user, product) is what most of the bookkeeping here is
 * for: a customer gets one review per product, so pairs are tracked as they
 * are used and a repeat draw is skipped rather than allowed to hit the
 * database and fail.
 */
class DemoReviewSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Copy by star rating. Written as whole reviews rather than assembled
     * from fragments — five-star filler reads as filler on a projector, and
     * the point of this table on screen is that someone said something.
     *
     * @var array<int, array<int, array{0: string, 1: string}>>
     */
    private const COPY = [
        5 => [
            ['Exactly what I wanted', 'Fit perfectly straight out of the box, no adjustment needed. The finish is much nicer in person than in the photos.'],
            ['Second pair from here', 'Bought the same model in another colour after wearing the first for a year. They hold up.'],
            ['Light enough to forget', 'I genuinely stop noticing them after ten minutes. Worth every dollar over the cheap pair I had before.'],
            ['Great with the anti-blue coating', 'Eight hours at a screen and no headache by the evening. That alone justified the add-on.'],
            ['Delivered faster than promised', 'Ordered on a Tuesday, had them by Thursday, and the prescription was spot on.'],
            ['Compliments every week', 'Three people have asked me where I got these. The shape suits my face far better than I expected.'],
        ],
        4 => [
            ['Very good, small caveat', 'Comfortable and well made. The nose pads needed one small adjustment at the shop, otherwise perfect.'],
            ['Happy with these', 'Look great and the lenses are clear. Slightly wider than I expected but I got used to them.'],
            ['Good value', 'Not the cheapest, but the build quality is obvious when you pick them up. Would buy again.'],
            ['Nearly perfect', 'The colour is a touch darker than the photo. Everything else is exactly as described.'],
            ['Solid everyday pair', 'Nothing flashy, just a well-made frame I can wear to work every day.'],
        ],
        3 => [
            ['Fine, not remarkable', 'They do the job. The hinges feel a little loose for the price, but nothing is wrong with them.'],
            ['Took some getting used to', 'The prescription is right but the frame sits lower than my old pair. Adjusting slowly.'],
            ['Average', 'Decent lenses, unexciting frame. I would probably try a different model next time.'],
        ],
        2 => [
            ['Not the right fit for me', 'The lenses are correct but the frame is too narrow across the temples and it pinches after an hour.'],
            ['Arrived scratched', 'There was a mark on the left lens out of the box. Support were helpful, but it should not have shipped like that.'],
        ],
        1 => [
            ['Wrong size entirely', 'Much smaller than the measurements suggested. Sending them back for an exchange.'],
        ],
    ];

    /** @var array<string, true> */
    private array $usedPairs = [];

    public function run(): void
    {
        $customers = User::where('role', 'customer')
            ->where('email', 'like', '%@'.DemoConfig::EMAIL_DOMAIN)
            ->pluck('id')
            ->all();

        if ($customers === []) {
            return;
        }

        $created = $this->reviewsFromPurchases();
        $created += $this->reviewsFromBrowsers($customers, DemoConfig::REVIEWS - $created);

        $this->command?->info("  Reviews written: {$created}");
    }

    /**
     * Reviews by the people who received the product — these get the
     * verified-purchase badge and a link back to the order.
     */
    private function reviewsFromPurchases(): int
    {
        $orders = Order::whereIn('status', ['delivered', 'shipped'])
            ->whereHas('user', fn ($q) => $q->where('email', 'like', '%@'.DemoConfig::EMAIL_DOMAIN))
            ->with(['eyeglasses:id,order_id,frame_id', 'contactLenses:id,order_id,contact_lens_id'])
            ->orderBy('id')
            ->get();

        $candidates = [];

        foreach ($orders as $order) {
            foreach ($order->eyeglasses as $line) {
                if ($line->frame_id !== null) {
                    $candidates[] = [$order, Frame::class, $line->frame_id];
                }
            }

            foreach ($order->contactLenses as $line) {
                if ($line->contact_lens_id !== null) {
                    $candidates[] = [$order, ContactLens::class, $line->contact_lens_id];
                }
            }
        }

        // Most people never write a review; roughly two in five here do,
        // which is generous but keeps the product pages populated.
        $target = min((int) round(DemoConfig::REVIEWS * 0.65), count($candidates));
        $created = 0;

        foreach (DemoRandom::pickMany($candidates, count($candidates)) as [$order, $type, $id]) {
            if ($created >= $target) {
                break;
            }

            $writtenAt = ($order->delivered_at ?? $order->created_at)
                ->toImmutable()
                ->addDays(DemoRandom::int(2, 30))
                ->setTime(DemoRandom::int(9, 23), DemoRandom::int(0, 59));

            if ($this->write($order->user_id, $type, $id, $order->id, $writtenAt)) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Reviews from customers who bought elsewhere or are reviewing an older
     * purchase — no order to point at, so no badge.
     *
     * @param  array<int, int>  $customers
     */
    private function reviewsFromBrowsers(array $customers, int $target): int
    {
        if ($target <= 0) {
            return 0;
        }

        $products = array_merge(
            array_map(fn ($id) => [Frame::class, $id], Frame::pluck('id')->all()),
            array_map(fn ($id) => [ContactLens::class, $id], ContactLens::pluck('id')->all()),
        );

        if ($products === []) {
            return 0;
        }

        $created = 0;

        // Bounded rather than a while-loop: once most pairs are taken, a
        // random draw mostly collides, and a run that cannot reach its target
        // should end rather than spin.
        for ($attempt = 0; $attempt < $target * 6 && $created < $target; $attempt++) {
            [$type, $id] = DemoRandom::pick($products);

            $writtenAt = DemoRandom::recentMoment(DemoConfig::HISTORY_DAYS);

            if ($this->write(DemoRandom::pick($customers), $type, $id, null, $writtenAt)) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Write one review, or return false if this customer has already reviewed
     * this product.
     */
    private function write(int $userId, string $type, int $productId, ?int $orderId, CarbonImmutable $writtenAt): bool
    {
        $key = "{$userId}|{$type}|{$productId}";

        if (isset($this->usedPairs[$key])) {
            return false;
        }

        $this->usedPairs[$key] = true;

        // Eyewear reviews skew high — people who chose the frame themselves
        // usually like it — but not so high that the rating filter and the
        // dashboard's distribution chart have nothing to show.
        $rating = (int) DemoRandom::weighted([5 => 46, 4 => 30, 3 => 13, 2 => 8, 1 => 3]);

        [$title, $body] = DemoRandom::pick(self::COPY[$rating]);

        $writtenAt = $writtenAt->min(CarbonImmutable::now());

        $review = Review::create([
            'reviewable_type' => $type,
            'reviewable_id' => $productId,
            'user_id' => $userId,
            'order_id' => $orderId,
            'rating' => $rating,
            'title' => $title,
            'body' => $body,
            'is_verified_purchase' => $orderId !== null,
            // Recent reviews are more likely to be sitting in the queue,
            // because nobody has got to them yet.
            'is_approved' => DemoRandom::chance($writtenAt->diffInDays(CarbonImmutable::now()) > 7 ? 92 : 55),
        ]);

        $review->forceFill(['created_at' => $writtenAt, 'updated_at' => $writtenAt])->save();

        $this->attachPhoto($review, $type);

        return true;
    }

    /**
     * A photo on some frame reviews — "here is how it actually looks on me"
     * is the single most useful thing a review of a frame can carry.
     *
     * Reuses the same real uploads the product cards draw on (see
     * DemoImages). With none on the disk it attaches nothing, rather than
     * writing rows that render as broken images.
     */
    private function attachPhoto(Review $review, string $type): void
    {
        $available = DemoImages::productShots();

        if ($available === [] || $type !== Frame::class || ! DemoRandom::chance(22)) {
            return;
        }

        foreach (DemoRandom::pickMany($available, DemoRandom::weighted([1 => 70, 2 => 30])) as $i => $path) {
            $review->images()->create(['path' => $path, 'sort_order' => $i]);
        }
    }
}
