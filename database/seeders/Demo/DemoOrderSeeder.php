<?php

namespace Database\Seeders\Demo;

use App\Models\ContactLens;
use App\Models\Frame;
use App\Models\Lens;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * The order book: a hundred and forty orders spread over five months, each
 * one written the way CheckoutController would have written it.
 *
 * That last part is the constraint everything here answers to. An order the
 * checkout could not have produced would look right in a list and fall apart
 * the moment anyone opened it — so line totals are recomputed from the
 * catalogue rather than invented, the snapshot columns carry the names and
 * prices that were current, every order gets its cash-on-delivery payment row
 * and its opening history entry, and the timestamps march forward in the
 * order the pipeline actually moves.
 *
 * Two other things are deliberate:
 *
 * Status is drawn from a distribution that depends on how old the order is.
 * A shop where a five-month-old order is still "pending" is not a shop, and
 * more usefully, weighting this way leaves a realistic handful of live orders
 * in the recent window for the console's queues to show.
 *
 * Orders thin out towards the past (see DemoRandom::recentMoment), so the
 * dashboard's revenue chart trends upward instead of sitting flat.
 */
class DemoOrderSeeder extends Seeder
{
    use WithoutModelEvents;

    /** @var array<int, string> */
    private const CARRIERS = ['Aramex', 'DHL Express', 'Wakilni', 'LibanPost', 'Shop courier'];

    /** @var array<int, string> */
    private const LINE_NOTES = [
        'Please tighten the temples slightly.',
        'Gift — no invoice in the box.',
        'Same fit as my last pair.',
        'Call before delivering, I am at the office until six.',
        'Second pair for reading only.',
    ];

    /** Every status the pipeline passes through on the way to the one it stops at. */
    private const PIPELINE = ['pending', 'paid', 'processing', 'shipped', 'delivered'];

    /** @var array<int, string> */
    private array $usedOrderNumbers = [];

    private Collection $frames;

    private Collection $lenses;

    private Collection $contactLenses;

    private Collection $customers;

    private Collection $riders;

    private Collection $consoleUsers;

    public function run(): void
    {
        $this->frames = Frame::where('is_active', true)->get();
        $this->lenses = Lens::where('is_active', true)->get()->load('features');
        $this->contactLenses = ContactLens::where('is_active', true)->get();

        $this->customers = User::where('role', 'customer')
            ->where('email', 'like', '%@'.DemoConfig::EMAIL_DOMAIN)
            ->with('profile')
            ->orderBy('id')
            ->get();

        $this->riders = User::where('role', 'delivery')
            ->where('email', 'like', '%@'.DemoConfig::EMAIL_DOMAIN)
            ->get();

        $this->consoleUsers = User::whereIn('role', ['owner', 'staff'])
            ->where('email', 'like', '%@'.DemoConfig::EMAIL_DOMAIN)
            ->get();

        if ($this->frames->isEmpty() || $this->lenses->isEmpty() || $this->customers->isEmpty()) {
            $this->command?->warn('  Nothing to build orders from — skipping.');

            return;
        }

        // A shop's order book is not evenly spread across its customers: a
        // minority come back repeatedly. Drawing the buyer from a list where
        // a third of the names appear twice reproduces that, which is what
        // gives the customer screens someone with a real order history to
        // open rather than forty people with one order each.
        $buyers = $this->customers->all();
        foreach (DemoRandom::pickMany($this->customers->all(), (int) ceil($this->customers->count() / 3)) as $repeat) {
            $buyers[] = $repeat;
            $buyers[] = $repeat;
        }

        for ($i = 0; $i < DemoConfig::ORDERS; $i++) {
            $this->createOrder(DemoRandom::pick($buyers));
        }
    }

    private function createOrder(User $customer): void
    {
        $placedAt = DemoRandom::recentMoment(DemoConfig::HISTORY_DAYS);
        $status = $this->statusFor($placedAt);

        $profile = $customer->profile;

        $order = Order::create([
            'user_id' => $customer->id,
            'order_number' => $this->orderNumber(),
            'status' => $status,
            'subtotal' => 0,
            'shipping_cost' => DemoConfig::SHIPPING_COST,
            'tax' => 0,
            'total' => 0,
            'shipping_address_line' => $profile?->address_line ?? 'Hamra Street',
            'shipping_city' => $profile?->city ?? 'Beirut',
            'shipping_postal_code' => $profile?->postal_code,
            'shipping_country' => $profile?->country ?? 'Lebanon',
        ]);

        $subtotal = $this->addLines($order, $customer, $placedAt);

        $totals = [
            'subtotal' => round($subtotal, 2),
            'shipping_cost' => DemoConfig::SHIPPING_COST,
            'tax' => round($subtotal * DemoConfig::TAX_RATE, 2),
        ];
        $totals['total'] = round($totals['subtotal'] + $totals['shipping_cost'] + $totals['tax'], 2);

        $timeline = $this->timeline($placedAt, $status);
        $lastTouched = $timeline === [] ? $placedAt : end($timeline);

        $order->forceFill($totals + [
            'paid_at' => $timeline['paid'] ?? null,
            'shipped_at' => $timeline['shipped'] ?? null,
            'delivered_at' => $timeline['delivered'] ?? null,
            'cancelled_at' => $timeline['cancelled'] ?? null,
            'estimated_delivery_date' => $placedAt->addDays(DemoRandom::int(3, 8))->toDateString(),
            'carrier' => isset($timeline['shipped']) ? DemoRandom::pick(self::CARRIERS) : null,
            'tracking_number' => isset($timeline['shipped']) ? $this->trackingNumber() : null,
            // A rider is assigned once the parcel is ready to leave, so an
            // order still being cut has nobody on it — which is exactly the
            // state the owner's "assign delivery" control exists to change.
            'assigned_delivery_user_id' => $this->riders->isNotEmpty() && in_array($status, ['processing', 'shipped', 'delivered', 'refunded'], true)
                ? DemoRandom::pick($this->riders->all())->id
                : null,
            'created_at' => $placedAt,
            'updated_at' => $lastTouched,
        ])->save();

        $this->writeHistory($order, $placedAt, $status, $timeline);
        $this->writePayment($order, $placedAt, $status, $timeline, (float) $totals['total']);
    }

    /**
     * Build the lines and return the subtotal.
     *
     * The mix matters: an order of nothing but frames never exercises the
     * contact-lens half of the schema, and an order of nothing but contact
     * lenses never exercises prescriptions or lens features. Most real
     * baskets are one configured pair, so that stays the common case, with
     * enough of the other two to fill both tables.
     */
    private function addLines(Order $order, User $customer, CarbonImmutable $placedAt): float
    {
        $shape = DemoRandom::weighted([
            'eyeglasses' => 58,
            'contacts' => 22,
            'mixed' => 12,
            'two_pairs' => 8,
        ]);

        $subtotal = 0.0;

        $eyeglassLines = match ($shape) {
            'eyeglasses', 'mixed' => 1,
            'two_pairs' => 2,
            default => 0,
        };

        for ($i = 0; $i < $eyeglassLines; $i++) {
            $subtotal += $this->addEyeglassLine($order, $customer, $placedAt);
        }

        if ($shape === 'contacts' || $shape === 'mixed') {
            foreach (DemoRandom::pickMany($this->contactLenses->all(), DemoRandom::int(1, 2)) as $product) {
                $subtotal += $this->addContactLensLine($order, $product, $placedAt);
            }
        }

        return $subtotal;
    }

    private function addEyeglassLine(Order $order, User $customer, CarbonImmutable $placedAt): float
    {
        /** @var Frame $frame */
        $frame = DemoRandom::pick($this->frames->all());

        // Sunglasses are bought without a prescription far more often than
        // not, so the plano package is the sensible default for them — and it
        // keeps the prescription columns legitimately empty on some lines.
        $planoLenses = $this->lenses->where('type', 'plano')->values();
        $prescriptionLenses = $this->lenses->where('type', '!=', 'plano')->values();

        $usePlano = $frame->category === 'sunglasses'
            ? DemoRandom::chance(70)
            : DemoRandom::chance(5);

        $pool = $usePlano && $planoLenses->isNotEmpty() ? $planoLenses : $prescriptionLenses;
        $pool = $pool->isEmpty() ? $this->lenses : $pool;

        /** @var Lens $lens */
        $lens = DemoRandom::pick($pool->all());

        // Only features the shop actually pairs with this package — the
        // lens_lens_feature pivot is there to stop someone selling a
        // polarised coating on a reading lens.
        $available = $lens->features->all();
        $features = $available === []
            ? []
            : DemoRandom::pickMany($available, DemoRandom::weighted([0 => 20, 1 => 30, 2 => 30, 3 => 20]));

        $featuresUnitPrice = array_sum(array_map(fn ($feature) => (float) $feature->price, $features));
        $quantity = DemoRandom::chance(88) ? 1 : 2;

        // Same arithmetic as PricingService::eyeglassLineTotal.
        $lineTotal = round(((float) $frame->price + (float) $lens->price + $featuresUnitPrice) * $quantity, 2);

        $prescription = $lens->type === 'plano' ? null : $this->prescriptionFor($customer);
        $values = $this->prescriptionSnapshot($prescription, $lens);

        $line = $order->eyeglasses()->create([
            'frame_id' => $frame->id,
            'lens_id' => $lens->id,
            'prescription_id' => $prescription?->id,
            'quantity' => $quantity,
            'frame_name' => $frame->name,
            'frame_brand' => $frame->brand,
            'lens_name' => $lens->name,
            'frame_unit_price' => $frame->price,
            'lens_unit_price' => $lens->price,
            'features_unit_price' => $featuresUnitPrice,
            'line_total' => $lineTotal,
            'notes' => DemoRandom::chance(18) ? DemoRandom::pick(self::LINE_NOTES) : null,
            ...$values,
        ]);

        foreach ($features as $feature) {
            $line->features()->create([
                'lens_feature_id' => $feature->id,
                'feature_name' => $feature->name,
                'unit_price' => $feature->price,
            ]);
        }

        $line->forceFill(['created_at' => $placedAt, 'updated_at' => $placedAt])->save();

        return $lineTotal;
    }

    private function addContactLensLine(Order $order, ContactLens $product, CarbonImmutable $placedAt): float
    {
        $quantity = DemoRandom::weighted([1 => 55, 2 => 30, 3 => 10, 4 => 5]);
        $lineTotal = round((float) $product->price * $quantity, 2);

        // Coloured lenses are routinely bought plano, purely for the tint.
        $plano = $product->color !== null && DemoRandom::chance(45);
        $toric = ! $plano && DemoRandom::chance(30);

        $line = $order->contactLenses()->create([
            'contact_lens_id' => $product->id,
            'quantity' => $quantity,
            'product_name' => $product->name,
            'brand' => $product->brand,
            'unit_price' => $product->price,
            'line_total' => $lineTotal,
            'right_power' => $plano ? null : DemoRandom::quarterStep(-6.00, -0.50),
            'left_power' => $plano ? null : DemoRandom::quarterStep(-6.00, -0.50),
            'right_cylinder' => $toric ? DemoRandom::quarterStep(-1.75, -0.75) : null,
            'left_cylinder' => $toric ? DemoRandom::quarterStep(-1.75, -0.75) : null,
            'right_axis' => $toric ? DemoRandom::int(0, 180) : null,
            'left_axis' => $toric ? DemoRandom::int(0, 180) : null,
        ]);

        $line->forceFill(['created_at' => $placedAt, 'updated_at' => $placedAt])->save();

        return $lineTotal;
    }

    /**
     * The customer's newest prescription that had not expired when they
     * ordered — or none, if they typed the numbers in by hand at checkout,
     * which the schema explicitly allows.
     */
    private function prescriptionFor(User $customer): ?Prescription
    {
        static $cache = [];

        $cache[$customer->id] ??= Prescription::where('user_id', $customer->id)
            ->orderByDesc('issued_at')
            ->get();

        $usable = $cache[$customer->id]->filter(fn (Prescription $p) => ! $p->isExpired());

        if ($usable->isEmpty() || DemoRandom::chance(18)) {
            return null;
        }

        return $usable->first();
    }

    /**
     * The per-eye numbers that go onto the line.
     *
     * Copied from the prescription on file when there is one — that is the
     * whole point of the link — and generated otherwise, because a
     * single-vision line with every optical column null would be an order the
     * lab could not fill.
     *
     * @return array<string, mixed>
     */
    private function prescriptionSnapshot(?Prescription $prescription, Lens $lens): array
    {
        if ($lens->type === 'plano') {
            return ['pd' => null];
        }

        if ($prescription !== null) {
            return [
                'right_sphere' => $prescription->right_sphere,
                'right_cylinder' => $prescription->right_cylinder,
                'right_axis' => $prescription->right_axis,
                'right_add' => $prescription->right_add,
                'left_sphere' => $prescription->left_sphere,
                'left_cylinder' => $prescription->left_cylinder,
                'left_axis' => $prescription->left_axis,
                'left_add' => $prescription->left_add,
                'pd' => $prescription->pd,
            ];
        }

        $astigmatism = DemoRandom::chance(50);
        $add = in_array($lens->type, ['progressive', 'bifocal', 'reading'], true);

        return [
            'right_sphere' => DemoRandom::quarterStep(-5.50, 2.00),
            'right_cylinder' => $astigmatism ? DemoRandom::quarterStep(-2.00, -0.25) : null,
            'right_axis' => $astigmatism ? DemoRandom::int(0, 180) : null,
            'right_add' => $add ? DemoRandom::quarterStep(0.75, 2.75) : null,
            'left_sphere' => DemoRandom::quarterStep(-5.50, 2.00),
            'left_cylinder' => $astigmatism ? DemoRandom::quarterStep(-2.00, -0.25) : null,
            'left_axis' => $astigmatism ? DemoRandom::int(0, 180) : null,
            'left_add' => $add ? DemoRandom::quarterStep(0.75, 2.75) : null,
            'pd' => DemoRandom::float(56.0, 70.0, 1),
        ];
    }

    /**
     * Where an order of this age has most likely got to.
     *
     * Anything old enough has finished one way or another; anything placed in
     * the last few days is probably still moving. Reading the three bands
     * top to bottom is a description of how the shop works.
     */
    private function statusFor(CarbonImmutable $placedAt): string
    {
        $age = $placedAt->diffInDays(CarbonImmutable::now());

        return (string) match (true) {
            $age > 21 => DemoRandom::weighted([
                'delivered' => 72, 'refunded' => 5, 'cancelled' => 8, 'shipped' => 5, 'processing' => 4, 'paid' => 3, 'pending' => 3,
            ]),
            $age > 6 => DemoRandom::weighted([
                'delivered' => 40, 'shipped' => 20, 'processing' => 15, 'paid' => 9, 'cancelled' => 9, 'pending' => 7,
            ]),
            default => DemoRandom::weighted([
                'processing' => 26, 'pending' => 26, 'shipped' => 20, 'paid' => 18, 'cancelled' => 6, 'delivered' => 4,
            ]),
        };
    }

    /**
     * When each hop happened, for the statuses this order actually reached.
     *
     * A cancelled order stops wherever it was cancelled, and a refunded one
     * has by definition been delivered first — so the two are handled apart
     * from the straight run down the pipeline.
     *
     * @return array<string, CarbonImmutable>
     */
    private function timeline(CarbonImmutable $placedAt, string $status): array
    {
        if ($status === 'cancelled') {
            return ['cancelled' => $placedAt->addHours(DemoRandom::int(2, 96))];
        }

        $reached = $status === 'refunded' ? self::PIPELINE : array_slice(self::PIPELINE, 0, array_search($status, self::PIPELINE, true) + 1);

        $at = $placedAt;
        $timeline = [];

        foreach ($reached as $step) {
            $at = $at->addHours(match ($step) {
                'pending' => 0,
                'paid' => DemoRandom::int(1, 30),
                'processing' => DemoRandom::int(2, 40),
                'shipped' => DemoRandom::int(6, 72),
                default => DemoRandom::int(12, 96),
            });

            $timeline[$step] = $at;
        }

        if ($status === 'refunded') {
            $timeline['refunded'] = $at->addDays(DemoRandom::int(1, 9));
        }

        // Nothing may claim to have happened in the future — an order placed
        // yesterday cannot have been delivered next week.
        $now = CarbonImmutable::now();

        return array_map(fn (CarbonImmutable $moment) => $moment->min($now), $timeline);
    }

    /**
     * @param  array<string, CarbonImmutable>  $timeline
     */
    private function writeHistory(Order $order, CarbonImmutable $placedAt, string $status, array $timeline): void
    {
        $notes = [
            'pending' => 'Order placed.',
            'paid' => 'Cash collected and recorded.',
            'processing' => 'Lenses cut and fitted to the frame.',
            'shipped' => 'Handed to the courier.',
            'delivered' => 'Delivered and signed for.',
            'cancelled' => 'Cancelled at the customer’s request.',
            'refunded' => 'Refund issued after an approved return.',
        ];

        $steps = ['pending' => $placedAt] + $timeline;

        if ($status === 'cancelled') {
            $steps = ['pending' => $placedAt, 'cancelled' => $timeline['cancelled']];
        }

        foreach ($steps as $step => $at) {
            $row = OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => $step,
                'note' => $notes[$step] ?? null,
                // The customer places the order; every hop after it is a
                // decision someone at the shop made, so it is attributable.
                'changed_by' => $step === 'pending'
                    ? $order->user_id
                    : ($this->consoleUsers->isEmpty() ? null : DemoRandom::pick($this->consoleUsers->all())->id),
            ]);

            $row->forceFill(['created_at' => $at])->save();
        }
    }

    /**
     * The shop is cash on delivery, so the payment row opens pending and is
     * settled when the rider hands the parcel over — mirroring what
     * Admin\OrderController::settlePayments does on a live status change.
     *
     * @param  array<string, CarbonImmutable>  $timeline
     */
    private function writePayment(Order $order, CarbonImmutable $placedAt, string $status, array $timeline, float $total): void
    {
        [$paymentStatus, $paidAt] = match ($status) {
            'paid', 'processing', 'shipped', 'delivered' => [Payment::STATUS_COMPLETED, $timeline['paid'] ?? null],
            'refunded' => [Payment::STATUS_REFUNDED, $timeline['paid'] ?? null],
            'cancelled' => [Payment::STATUS_FAILED, null],
            default => [Payment::STATUS_PENDING, null],
        };

        $payment = Payment::create([
            'order_id' => $order->id,
            'method' => Payment::METHOD_CASH_ON_DELIVERY,
            'status' => $paymentStatus,
            'transaction_id' => null,
            'amount' => $total,
            'currency' => 'USD',
            'paid_at' => $paidAt,
            'notes' => match ($paymentStatus) {
                Payment::STATUS_COMPLETED => 'Collected on delivery.',
                Payment::STATUS_REFUNDED => 'Refunded in cash after return.',
                Payment::STATUS_FAILED => 'Order cancelled before collection.',
                default => null,
            },
        ]);

        $payment->forceFill([
            'created_at' => $placedAt,
            'updated_at' => $paidAt ?? $placedAt,
        ])->save();
    }

    /**
     * OPT- plus eight characters, the same shape CheckoutController generates,
     * tracked so the unique index can never be hit twice in one run.
     */
    private function orderNumber(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        do {
            $suffix = '';

            for ($i = 0; $i < 8; $i++) {
                $suffix .= $alphabet[DemoRandom::int(0, strlen($alphabet) - 1)];
            }

            $number = 'OPT-'.$suffix;
        } while (isset($this->usedOrderNumbers[$number]));

        $this->usedOrderNumbers[$number] = true;

        return $number;
    }

    private function trackingNumber(): string
    {
        return sprintf('%s%09d', DemoRandom::pick(['ARX', 'DHL', 'WKL', 'LBP']), DemoRandom::int(1, 999999999));
    }
}
