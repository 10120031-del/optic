<?php

namespace App\Services;

use App\Models\Collection as ProductCollection;
use App\Models\ContactLens;
use App\Models\Frame;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Prescription;
use App\Models\Review;
use App\Models\User;
use App\Notifications\InboxNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The shop's notification catalogue: every event worth telling someone
 * about, the wording it gets, and who receives it.
 *
 * Almost nothing calls this from a controller. The observers in App\Observers
 * watch the lifecycle of orders, returns, reviews, prescriptions, products and
 * accounts and call the matching method here, so a notification cannot be
 * forgotten by a new code path that touches the same model. The seeders are
 * the one place that must not fire them, and they already run
 * WithoutModelEvents.
 *
 * The exception is collectionAnnounced(). Announcing a collection is not
 * something that happens to a model — it is a decision the owner makes on
 * a collection that may have existed for weeks — so it hangs off an explicit
 * controller action rather than an observer. Saving a collection must never
 * mail the customer list.
 *
 * Three audiences:
 *   - the customer, who hears about their own order/return/prescription/review;
 *   - the owner and any other staff account, who hear about anything needing
 *     a decision (new order, return to settle, review to moderate,
 *     prescription to verify) or a restock;
 *   - every customer at once, but only for an announced collection.
 *
 * Every url is built with absolute: false. The inbox redirects the reader to
 * whatever is stored, so keeping it a path means it can never point off-site
 * and stays correct whichever host the app is served from.
 */
class Notifier
{
    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    */

    public function orderPlaced(Order $order): void
    {
        $total = $this->money($order->total);

        $this->toUser($order->user, new InboxNotification(
            event: 'order.placed',
            title: __('Order :number confirmed', ['number' => $order->order_number]),
            body: __('We have your order for :total. Payment is cash on delivery — we will let you know as soon as it ships.', ['total' => $total]),
            url: route('orders.show', $order, absolute: false),
            level: 'success',
        ));

        $this->toStaff(new InboxNotification(
            event: 'admin.order.placed',
            title: __('New order :number — :total', ['number' => $order->order_number, 'total' => $total]),
            body: __('Placed by :customer, shipping to :city.', [
                'customer' => $this->name($order->user),
                'city' => $order->shipping_city,
            ]),
            url: route('admin.orders.show', $order, absolute: false),
            level: 'success',
        ));
    }

    /**
     * Fired on every hop of the pipeline the owner drives from the staff
     * console. Only the customer hears about it — staff just made the change.
     */
    public function orderStatusChanged(Order $order): void
    {
        [$title, $body, $level] = match ($order->status) {
            'paid' => [
                __('Payment recorded for order :number', ['number' => $order->order_number]),
                __('We have marked your :total as received. Thank you!', ['total' => $this->money($order->total)]),
                'success',
            ],
            'processing' => [
                __('Order :number is being prepared', ['number' => $order->order_number]),
                __('Your lenses are being cut and fitted. We will tell you the moment it leaves us.'),
                'info',
            ],
            'shipped' => [
                __('Order :number is on its way', ['number' => $order->order_number]),
                $this->shippingLine($order),
                'info',
            ],
            'delivered' => [
                __('Order :number was delivered', ['number' => $order->order_number]),
                __('Enjoy your new eyewear — and do leave a review, it helps other shoppers.'),
                'success',
            ],
            'cancelled' => [
                __('Order :number was cancelled', ['number' => $order->order_number]),
                __('Nothing was charged. Get in touch if this was not what you expected.'),
                'danger',
            ],
            'refunded' => [
                __('Order :number was refunded', ['number' => $order->order_number]),
                __('The refund has been recorded against your order.'),
                'warn',
            ],
            default => [
                __('Order :number is now :status', ['number' => $order->order_number, 'status' => $this->humanise($order->status)]),
                null,
                'info',
            ],
        };

        $this->toUser($order->user, new InboxNotification(
            event: 'order.status',
            title: $title,
            body: $body,
            url: route('orders.show', $order, absolute: false),
            level: $level,
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Returns and exchanges
    |--------------------------------------------------------------------------
    */

    public function returnRequested(OrderReturn $return): void
    {
        $order = $return->order;
        $kind = $return->type === 'exchange' ? __('exchange') : __('return');

        $this->toUser($return->requestedBy, new InboxNotification(
            event: 'return.requested',
            title: __('We received your :kind request', ['kind' => $kind]),
            body: __('Request #:id against order :number. Our team will review it and come back to you.', [
                'id' => $return->id,
                'number' => $order?->order_number,
            ]),
            url: $order ? route('orders.show', $order, absolute: false) : null,
            level: 'info',
        ));

        $this->toStaff(new InboxNotification(
            event: 'admin.return.requested',
            title: __('New :kind request on order :number', ['kind' => $kind, 'number' => $order?->order_number]),
            body: __('Reason given: :reason.', ['reason' => $this->humanise($return->reason)]),
            url: route('admin.returns.show', $return, absolute: false),
            level: 'warn',
        ));
    }

    public function returnStatusChanged(OrderReturn $return): void
    {
        $kind = $return->type === 'exchange' ? __('exchange') : __('return');

        [$title, $body, $level] = match ($return->status) {
            'approved' => [
                __('Your :kind was approved', ['kind' => $kind]),
                __('Send the item back to us and we will take it from there.'),
                'success',
            ],
            'rejected' => [
                __('Your :kind request was declined', ['kind' => $kind]),
                $return->staff_notes ?: __('Get in touch if you would like us to look at it again.'),
                'danger',
            ],
            'item_received' => [
                __('We have received your returned item'),
                __('It is being checked now — your refund or exchange follows shortly.'),
                'info',
            ],
            'refunded' => [
                __('Your refund has been issued'),
                __('Refund of :amount recorded against order :number.', [
                    'amount' => $this->money($return->refund_amount),
                    'number' => $return->order?->order_number,
                ]),
                'success',
            ],
            'exchanged' => [
                __('Your exchange is on the way'),
                __('The replacement order has been created for you.'),
                'success',
            ],
            default => [
                __('Your :kind request is now :status', ['kind' => $kind, 'status' => $this->humanise($return->status)]),
                $return->staff_notes ?: null,
                'info',
            ],
        };

        $this->toUser($return->requestedBy, new InboxNotification(
            event: 'return.status',
            title: $title,
            body: $body,
            url: $return->order ? route('orders.show', $return->order, absolute: false) : null,
            level: $level,
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Reviews
    |--------------------------------------------------------------------------
    */

    /** Reviews are held for moderation, so a new one is the owner's queue. */
    public function reviewSubmitted(Review $review): void
    {
        $this->toStaff(new InboxNotification(
            event: 'admin.review.submitted',
            title: __(':stars-star review awaiting approval', ['stars' => $review->rating]),
            body: __(':customer reviewed :product.', [
                'customer' => $this->name($review->user),
                'product' => $review->reviewable?->name ?? __('a product'),
            ]),
            url: route('admin.reviews.index', ['filter' => 'pending'], absolute: false),
            // A one- or two-star review is a complaint as much as a review.
            level: $review->rating <= 2 ? 'warn' : 'info',
        ));
    }

    public function reviewApproved(Review $review): void
    {
        $this->toUser($review->user, new InboxNotification(
            event: 'review.approved',
            title: __('Your review is now live'),
            body: __('Thanks for reviewing :product — it is showing on the product page.', [
                'product' => $review->reviewable?->name ?? __('the product'),
            ]),
            url: $this->productUrl($review),
            level: 'success',
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Prescriptions
    |--------------------------------------------------------------------------
    */

    public function prescriptionUploaded(Prescription $prescription): void
    {
        // Only an uploaded scan needs a human to check it against the numbers;
        // a prescription typed in without one has nothing to verify against.
        if ($prescription->file_path === null) {
            return;
        }

        $this->toStaff(new InboxNotification(
            event: 'admin.prescription.uploaded',
            title: __('Prescription awaiting verification'),
            body: __(':customer uploaded a prescription scan.', ['customer' => $this->name($prescription->user)]),
            url: route('admin.prescriptions.show', $prescription, absolute: false),
            level: 'info',
        ));
    }

    public function prescriptionVerified(Prescription $prescription): void
    {
        $this->toUser($prescription->user, new InboxNotification(
            event: 'prescription.verified',
            title: __('Your prescription has been verified'),
            body: __('Our team checked it against your upload — it is ready to use at checkout.'),
            url: route('prescriptions.index', absolute: false),
            level: 'success',
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Stock
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Frame|ContactLens  $product
     */
    public function stockRunningLow(Model $product): void
    {
        $this->toStaff(new InboxNotification(
            event: 'admin.stock.low',
            title: __('Low stock: :product', ['product' => $product->name]),
            body: __('Only :count left. Reorder before it sells out.', ['count' => $product->stock]),
            url: $this->stockUrl($product),
            level: 'warn',
        ));
    }

    /**
     * @param  Frame|ContactLens  $product
     */
    public function stockRanOut(Model $product): void
    {
        $this->toStaff(new InboxNotification(
            event: 'admin.stock.out',
            title: __('Out of stock: :product', ['product' => $product->name]),
            body: __('The last one just went. Shoppers now see it badged as out of stock.'),
            url: $this->stockUrl($product),
            level: 'danger',
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Collections
    |--------------------------------------------------------------------------
    */

    /**
     * The owner has announced a curated drop.
     *
     * The only broadcast in this catalogue: everything else here tells one
     * person about their own business, this tells the whole shop. Every
     * customer gets the inbox row; the e-mail that goes out alongside it is
     * the controller's job, because it is gated on newsletter_opt_in and
     * only marketing consent belongs behind that gate — an inbox row on a
     * site they chose to visit does not.
     *
     * @return int  How many customers were told, for the owner's receipt.
     */
    public function collectionAnnounced(ProductCollection $collection): int
    {
        $customers = User::where('role', 'customer')->get();

        $notification = new InboxNotification(
            event: 'collection.announced',
            title: __('New collection: :name', ['name' => $collection->name]),
            body: $collection->description
                ? Str::limit($collection->description, 140)
                : __(':count new pieces just landed. Take a look before they go.', ['count' => $collection->itemCount()]),
            url: route('collections.show', $collection, absolute: false),
            level: 'success',
        );

        $customers->each->notify($notification);

        $this->toStaff(new InboxNotification(
            event: 'admin.collection.announced',
            title: __('":name" is live', ['name' => $collection->name]),
            body: __('Announced to :count customer(s). Newsletter subscribers are also getting it by e-mail.', [
                'count' => $customers->count(),
            ]),
            url: route('admin.collections.edit', $collection, absolute: false),
            level: 'success',
        ));

        return $customers->count();
    }

    /*
    |--------------------------------------------------------------------------
    | Accounts
    |--------------------------------------------------------------------------
    */

    public function customerRegistered(User $customer): void
    {
        $this->toUser($customer, new InboxNotification(
            event: 'account.welcome',
            title: __('Welcome to Lucent Optics'),
            body: __('Order updates, prescription checks and review news all land here in your inbox.'),
            url: route('frames.index', absolute: false),
            level: 'success',
        ));

        $this->toStaff(new InboxNotification(
            event: 'admin.customer.registered',
            title: __('New customer: :name', ['name' => $this->name($customer)]),
            body: $customer->email,
            url: route('admin.dashboard', absolute: false),
            level: 'info',
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Plumbing
    |--------------------------------------------------------------------------
    */

    private function toUser(?User $user, InboxNotification $notification): void
    {
        // A deleted account leaves nobody to tell.
        $user?->notify($notification);
    }

    /** Everyone with a staff account, so a shop with two owners doesn't lose half its alerts. */
    private function toStaff(InboxNotification $notification): void
    {
        $this->staff()->each->notify($notification);
    }

    /** @return Collection<int, User> */
    private function staff(): Collection
    {
        return User::whereIn('role', ['owner', 'staff', 'admin'])->get();
    }

    private function shippingLine(Order $order): string
    {
        if ($order->carrier && $order->tracking_number) {
            return __('Sent with :carrier, tracking :tracking.', [
                'carrier' => $order->carrier,
                'tracking' => $order->tracking_number,
            ]);
        }

        if ($order->estimated_delivery_date) {
            return __('Estimated delivery :date.', ['date' => $order->estimated_delivery_date->format('M j, Y')]);
        }

        return __('It has left us and is with the courier now.');
    }

    private function stockUrl(Model $product): string
    {
        return $product instanceof Frame
            ? route('admin.frames.edit', $product, absolute: false)
            : route('admin.contact-lenses.edit', $product, absolute: false);
    }

    private function productUrl(Review $review): ?string
    {
        return match ($review->reviewable_type) {
            Frame::class => route('frames.show', $review->reviewable_id, absolute: false),
            ContactLens::class => route('contact-lenses.show', $review->reviewable_id, absolute: false),
            default => null,
        };
    }

    private function name(?User $user): string
    {
        return $user ? trim($user->first_name.' '.$user->last_name) : __('A customer');
    }

    private function money(mixed $amount): string
    {
        return '$'.number_format((float) $amount, 2);
    }

    private function humanise(?string $value): string
    {
        return str_replace('_', ' ', (string) $value);
    }
}
