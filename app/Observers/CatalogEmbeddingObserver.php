<?php

namespace App\Observers;

use App\Models\ContactLens;
use App\Models\Frame;
use App\Models\ProductEmbedding;
use App\Services\CatalogEmbedder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Keeps stored embeddings honest when the catalog changes underneath them.
 *
 * It invalidates rather than re-embeds. Running the model takes seconds and
 * needs Node, and the deployment guide promises Node is a build-time
 * requirement only — so an admin saving a frame must not be the thing that
 * drags a 23MB transformer onto the web server.
 *
 * Dropping the row instead is safe because the Recommender falls back to
 * attribute scoring for any product without a vector: an edited frame keeps
 * getting recommendations, just from the cheaper signal, until the next
 * `php artisan catalog:embed` restores its semantic one.
 *
 * Only fires when the *described* text actually changed. Selling one frame
 * moves the stock column, which no embedding depends on, and re-embedding
 * the catalog after every checkout would be pure waste.
 */
class CatalogEmbeddingObserver
{
    public function __construct(private readonly CatalogEmbedder $embedder) {}

    /**
     * @param  Frame|ContactLens  $product
     */
    public function updated(Model $product): void
    {
        $embedding = $this->embeddingFor($product);

        if (! $embedding) {
            return;
        }

        if ($embedding->content_hash === hash('sha256', $this->embedder->describe($product))) {
            return;
        }

        $embedding->delete();

        Cache::forget(CatalogEmbedder::CACHE_KEY);
    }

    /**
     * @param  Frame|ContactLens  $product
     */
    public function deleted(Model $product): void
    {
        $this->embeddingFor($product)?->delete();

        Cache::forget(CatalogEmbedder::CACHE_KEY);
    }

    /**
     * A newly created product has no vector yet, so there is nothing to
     * invalidate — but the cached matrix is now missing a row it should
     * eventually have, and the fallback needs a clean read.
     */
    public function created(Model $product): void
    {
        Cache::forget(CatalogEmbedder::CACHE_KEY);
    }

    private function embeddingFor(Frame|ContactLens $product): ?ProductEmbedding
    {
        return ProductEmbedding::where('embeddable_type', $product->getMorphClass())
            ->where('embeddable_id', $product->getKey())
            ->first();
    }
}
