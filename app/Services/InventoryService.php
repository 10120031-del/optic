<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\ContactLens;
use App\Models\Frame;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * What is actually on the shelf.
 *
 * Stock comes off when the order is written, not when something is dropped in
 * a cart — a cart can sit for weeks, and holding stock against every abandoned
 * one would make the shop look empty. The trade is that two shoppers can both
 * be carrying the last frame, so commit() is the moment that gets resolved:
 * it runs inside CheckoutController's transaction, takes a row lock on each
 * product, re-reads the counts, and refuses the order if the shelf has since
 * emptied. Whoever posts second is told, rather than sold something that
 * isn't there.
 *
 * Only frames and contact lenses are counted. Lens packages and lens features
 * are ground to order, so they have no stock column to draw down.
 */
class InventoryService
{
    /**
     * Take a cart's units off the shelf. Call inside a transaction.
     *
     * @throws ValidationException if the shelf can no longer cover the cart
     */
    public function commit(Cart $cart): void
    {
        $wanted = $this->demand($cart);

        // lockForUpdate holds these rows for the rest of the transaction, so a
        // second checkout racing for the same frame waits here and then reads
        // the count *after* the first one has taken its units.
        $frames = $this->lock(Frame::query(), $wanted[Frame::class]);
        $lenses = $this->lock(ContactLens::query(), $wanted[ContactLens::class]);

        $short = array_merge(
            $this->shortfall($wanted[Frame::class], $frames),
            $this->shortfall($wanted[ContactLens::class], $lenses),
        );

        if ($short !== []) {
            throw ValidationException::withMessages(['cart' => $this->messages($short)]);
        }

        foreach ($wanted[Frame::class] as $id => $units) {
            $frames->get($id)->decrement('stock', $units);
        }

        foreach ($wanted[ContactLens::class] as $id => $units) {
            $lenses->get($id)->decrement('stock', $units);
        }
    }

    /**
     * Put a cancelled order's units back.
     *
     * A cancelled order never shipped, so nothing left the shelf and the
     * count has to be made whole again — otherwise every cancellation would
     * quietly destroy inventory. Returns and refunds deliberately do *not*
     * come through here: staff inspect what comes back (see the return
     * items' condition_notes) and restock it by hand if it is fit to resell.
     */
    public function restore(Order $order): void
    {
        $returned = $this->tally(
            $order->eyeglasses, 'frame_id',
            $order->contactLenses, 'contact_lens_id',
        );

        foreach ($this->lock(Frame::query(), $returned[Frame::class]) as $id => $frame) {
            $frame->increment('stock', $returned[Frame::class][$id]);
        }

        foreach ($this->lock(ContactLens::query(), $returned[ContactLens::class]) as $id => $lens) {
            $lens->increment('stock', $returned[ContactLens::class][$id]);
        }
    }

    /**
     * What the cart wants more of than the shelf holds, without locking
     * anything — for warning the shopper before they try to check out.
     *
     * @return array<int, array{name: string, wanted: int, available: int}>
     */
    public function shortages(Cart $cart): array
    {
        $wanted = $this->demand($cart);

        return array_merge(
            $this->shortfall($wanted[Frame::class], $this->find(Frame::query(), $wanted[Frame::class])),
            $this->shortfall($wanted[ContactLens::class], $this->find(ContactLens::query(), $wanted[ContactLens::class])),
        );
    }

    /**
     * @param  array<int, array{name: string, wanted: int, available: int}>  $shortages
     * @return array<int, string>
     */
    public function messages(array $shortages): array
    {
        return array_map(fn (array $s) => $s['available'] === 0
            ? __(':product has just sold out.', ['product' => $s['name']])
            : __('Only :available of :product left — your cart has :wanted.', [
                'available' => $s['available'],
                'product' => $s['name'],
                'wanted' => $s['wanted'],
            ]),
            $shortages);
    }

    /**
     * Units of each product the cart would consume. The same frame can appear
     * on several lines (one per lens choice), so the lines are summed rather
     * than checked one at a time — three lines of one apiece need three in
     * stock, not one.
     *
     * @return array<class-string, array<int, int>>
     */
    private function demand(Cart $cart): array
    {
        return $this->tally(
            $cart->eyeglasses, 'frame_id',
            $cart->contactLenses, 'contact_lens_id',
        );
    }

    /**
     * @param  iterable<object>  $eyeglassLines
     * @param  iterable<object>  $contactLensLines
     * @return array<class-string, array<int, int>>
     */
    private function tally(iterable $eyeglassLines, string $frameKey, iterable $contactLensLines, string $lensKey): array
    {
        $tally = [Frame::class => [], ContactLens::class => []];

        foreach ([[Frame::class, $eyeglassLines, $frameKey], [ContactLens::class, $contactLensLines, $lensKey]] as [$class, $lines, $key]) {
            foreach ($lines as $line) {
                // Order lines keep their product id nullable, so a frame
                // deleted from the catalogue leaves nothing to count.
                if ($line->{$key} === null) {
                    continue;
                }

                $tally[$class][$line->{$key}] = ($tally[$class][$line->{$key}] ?? 0) + (int) $line->quantity;
            }
        }

        return $tally;
    }

    /**
     * @param  array<int, int>  $wanted
     * @param  Collection<int, Frame|ContactLens>  $products
     * @return array<int, array{name: string, wanted: int, available: int}>
     */
    private function shortfall(array $wanted, Collection $products): array
    {
        $short = [];

        foreach ($wanted as $id => $units) {
            $product = $products->get($id);
            $available = (int) ($product->stock ?? 0);

            if ($available >= $units) {
                continue;
            }

            $short[] = [
                'name' => $product->name ?? __('A product in your cart'),
                'wanted' => $units,
                'available' => $available,
            ];
        }

        return $short;
    }

    /**
     * @param  Builder<Frame|ContactLens>  $query
     * @param  array<int, int>  $wanted
     * @return Collection<int, Frame|ContactLens>
     */
    private function lock(Builder $query, array $wanted): Collection
    {
        return $this->find($query->lockForUpdate(), $wanted);
    }

    /**
     * @param  Builder<Frame|ContactLens>  $query
     * @param  array<int, int>  $wanted
     * @return Collection<int, Frame|ContactLens>
     */
    private function find(Builder $query, array $wanted): Collection
    {
        if ($wanted === []) {
            return new Collection;
        }

        return $query->whereIn('id', array_keys($wanted))->get()->keyBy('id');
    }
}
