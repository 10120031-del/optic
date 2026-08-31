<?php

namespace App\Observers;

use App\Models\ContactLens;
use App\Models\Frame;
use App\Services\Notifier;
use Illuminate\Database\Eloquent\Model;

/**
 * Watches the stock column on both sellable product types.
 *
 * Frames and contact lenses run out at different rates, so each model carries
 * its own LOW_STOCK_THRESHOLD — the same number the dashboard's low-stock
 * panel and the storefront's "Low stock" badge read.
 *
 * Alerts fire on the *crossing*, not on every save: dropping from 7 to 4
 * warns once, 4 to 3 says nothing more, and restocking to 20 then selling
 * back down to 4 warns again. Otherwise a busy day would bury the owner's
 * inbox in the same warning.
 */
class StockObserver
{
    public function __construct(private readonly Notifier $notifier) {}

    /**
     * @param  Frame|ContactLens  $product
     */
    public function updated(Model $product): void
    {
        if (! $product->wasChanged('stock')) {
            return;
        }

        $before = (int) $product->getOriginal('stock');
        $after = (int) $product->stock;

        // A restock is good news, and nobody needs telling about it.
        if ($after >= $before) {
            return;
        }

        if ($after === 0) {
            $this->notifier->stockRanOut($product);

            return;
        }

        if ($after <= $product::LOW_STOCK_THRESHOLD && $before > $product::LOW_STOCK_THRESHOLD) {
            $this->notifier->stockRunningLow($product);
        }
    }
}
