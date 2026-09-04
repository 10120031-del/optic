<?php

namespace Database\Seeders\Demo;

use App\Models\Order;
use App\Models\OrderContactLens;
use App\Models\OrderEyeglass;
use App\Models\OrderReturn;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Returns and exchanges against delivered orders.
 *
 * Eyewear has an unusually high return rate — nobody can try a frame on
 * before it arrives — so this is a queue the shop genuinely works, not an
 * edge case. Every status the table models gets at least one row: requests
 * still waiting on a decision, approved ones waiting on the parcel, parcels
 * received, refunds paid, exchanges settled, and rejections.
 *
 * A return only ever covers lines that are on the order it belongs to, which
 * is the one invariant the polymorphic order_return_items table cannot
 * enforce for itself.
 */
class DemoReturnSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * What people write in the box, keyed by the reason they picked. Reading
     * the wrong-prescription and wrong-size entries next to each other is
     * most of the argument for the face-match feature existing.
     *
     * @var array<string, array<int, string>>
     */
    private const DETAILS = [
        'wrong_prescription' => [
            'Everything is sharp at arm’s length but blurry further out — I think the axis is off.',
            'The right lens gives me a headache after about twenty minutes. My old pair does not.',
        ],
        'wrong_size_fit' => [
            'The frame is wider than my face and slides down constantly.',
            'Temples are too short; they press behind my ears by the afternoon.',
            'Looked right on screen, far too big in person.',
        ],
        'damaged_or_defective' => [
            'The left hinge was loose out of the box and has since come apart.',
            'There is a scratch across the right lens that was there when I opened it.',
        ],
        'not_as_described' => [
            'The listing says tortoise but they are almost solid brown.',
            'Photographed as a matte finish, arrived glossy.',
        ],
        'changed_mind' => [
            'Bought two pairs and only need one.',
            'Found the same frame in a colour I prefer.',
        ],
        'other' => [
            'Ordered the wrong model by mistake — my fault entirely.',
        ],
    ];

    /** @var array<string, array<int, string>> */
    private const STAFF_NOTES = [
        'approved' => ['Approved — return label sent to the customer.'],
        'rejected' => ['Outside the 14-day window and the frame shows wear. Declined, explained by phone.'],
        'item_received' => ['Parcel arrived, frame is in resalable condition. Awaiting refund run.'],
        'refunded' => ['Refund handed over in cash. Stock returned to the shelf.'],
        'exchanged' => ['Replacement pair cut and shipped under a new order.'],
    ];

    public function run(): void
    {
        $orders = Order::whereIn('status', ['delivered', 'refunded'])
            ->whereHas('user', fn ($q) => $q->where('email', 'like', '%@'.DemoConfig::EMAIL_DOMAIN))
            ->with(['eyeglasses:id,order_id', 'contactLenses:id,order_id'])
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            return;
        }

        $resolvers = User::whereIn('role', ['owner', 'staff'])
            ->where('email', 'like', '%@'.DemoConfig::EMAIL_DOMAIN)
            ->pluck('id')
            ->all();

        // Every status gets at least one row, then the rest are drawn from a
        // realistic spread. A queue screen with one row in it demonstrates
        // nothing; a queue screen missing a state demonstrates less.
        $statuses = ['requested', 'approved', 'rejected', 'item_received', 'refunded', 'exchanged'];

        while (count($statuses) < DemoConfig::RETURNS) {
            $statuses[] = (string) DemoRandom::weighted([
                'requested' => 24, 'approved' => 18, 'refunded' => 24, 'item_received' => 14, 'exchanged' => 12, 'rejected' => 8,
            ]);
        }

        foreach (DemoRandom::pickMany($orders->all(), min(count($statuses), $orders->count())) as $i => $order) {
            $this->createReturn($order, $statuses[$i], $resolvers);
        }
    }

    /**
     * @param  array<int, int>  $resolvers
     */
    private function createReturn(Order $order, string $status, array $resolvers): void
    {
        $lines = array_merge(
            $order->eyeglasses->map(fn (OrderEyeglass $line) => [OrderEyeglass::class, $line->id])->all(),
            $order->contactLenses->map(fn (OrderContactLens $line) => [OrderContactLens::class, $line->id])->all(),
        );

        if ($lines === []) {
            return;
        }

        $reason = (string) DemoRandom::weighted([
            'wrong_size_fit' => 30,
            'wrong_prescription' => 22,
            'changed_mind' => 18,
            'not_as_described' => 14,
            'damaged_or_defective' => 12,
            'other' => 4,
        ]);

        $type = $status === 'exchanged' || DemoRandom::chance(30) ? 'exchange' : 'return';

        $delivered = ($order->delivered_at ?? $order->created_at)->toImmutable();
        $requestedAt = $delivered->addDays(DemoRandom::int(1, 14))->setTime(DemoRandom::int(9, 21), DemoRandom::int(0, 59));
        $requestedAt = $requestedAt->min(CarbonImmutable::now());

        $settled = ! in_array($status, ['requested'], true);
        $resolvedAt = $settled
            ? $requestedAt->addDays(DemoRandom::int(1, 10))->min(CarbonImmutable::now())
            : null;

        // Only a return that got as far as the money moving carries an
        // amount, and it never exceeds what was actually paid for the order.
        $refundAmount = in_array($status, ['refunded'], true)
            ? min((float) $order->total, round((float) $order->subtotal * DemoRandom::float(0.5, 1.0), 2))
            : null;

        $return = OrderReturn::create([
            'order_id' => $order->id,
            'requested_by' => $order->user_id,
            'type' => $type,
            'reason' => $reason,
            'reason_details' => DemoRandom::pick(self::DETAILS[$reason]),
            'status' => $status,
            'refund_amount' => $refundAmount,
            'exchange_order_id' => $status === 'exchanged' ? $this->replacementOrderFor($order) : null,
            'staff_notes' => self::STAFF_NOTES[$status][0] ?? null,
            'resolved_by' => $settled && $resolvers !== [] ? DemoRandom::pick($resolvers) : null,
            'resolved_at' => $resolvedAt,
        ]);

        $return->forceFill([
            'created_at' => $requestedAt,
            'updated_at' => $resolvedAt ?? $requestedAt,
        ])->save();

        // A return does not have to cover the whole order, and usually does
        // not when the order had more than one line.
        foreach (DemoRandom::pickMany($lines, DemoRandom::chance(75) ? 1 : count($lines)) as [$lineType, $lineId]) {
            $item = $return->items()->create([
                'returnable_type' => $lineType,
                'returnable_id' => $lineId,
                'quantity' => 1,
                'condition_notes' => match ($status) {
                    'item_received', 'refunded', 'exchanged' => DemoRandom::pick([
                        'Unworn, original case and cloth included.',
                        'Light wear on the temples, otherwise resalable.',
                        'Lenses unmarked. Frame returned to stock.',
                    ]),
                    default => null,
                },
            ]);

            $item->forceFill(['created_at' => $requestedAt, 'updated_at' => $requestedAt])->save();
        }
    }

    /**
     * The replacement order created for a settled exchange.
     *
     * Reuses a later order the same customer placed rather than inventing
     * one, so the link always points at a real, openable order — the column
     * exists for the paper trail, and a trail that dead-ends is worse than
     * none.
     */
    private function replacementOrderFor(Order $order): ?int
    {
        return Order::where('user_id', $order->user_id)
            ->where('id', '!=', $order->id)
            ->where('created_at', '>', $order->created_at)
            ->orderBy('created_at')
            ->value('id');
    }
}
