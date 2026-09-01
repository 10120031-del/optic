<?php

namespace App\Services;

use App\Models\ContactLens;
use App\Models\Frame;
use App\Models\Order;
use App\Models\OrderContactLens;
use App\Models\OrderEyeglass;
use App\Models\ProductEmbedding;
use App\Models\ProductView;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Product recommendations for frames and contact lenses.
 *
 * A hybrid recommender over two kinds of evidence:
 *
 *   SEMANTIC — where a product sits in the vector space of the
 *   all-MiniLM-L6-v2 sentence transformer. App\Services\CatalogEmbedder
 *   writes a paragraph describing each product and stores the model's
 *   384-dimensional embedding of it; similarity here is the cosine between
 *   two of those vectors. This is what "similar" means now, and it needs no
 *   sales history at all, which is what makes the shop useful on day one.
 *
 *   BEHAVIOURAL — what people actually did, from the tables the shop
 *   already keeps. Co-view (people who opened this also opened that, from
 *   product_views) and co-purchase (customers who bought this also bought
 *   that, from the order lines). The model cannot know these: no amount of
 *   reading a product description reveals that buyers of a frame come back
 *   for contact lenses a month later.
 *
 * The two are complementary and both are needed. Semantics without
 * behaviour recommends things that merely read alike; behaviour without
 * semantics recommends nothing until the shop has traffic.
 *
 * Vectors are stored L2-normalized, so every cosine below is a plain dot
 * product. Both collaborative signals are damped by the candidate's own
 * popularity — divided by the square root of its total audience — so the
 * shop's best-seller does not end up recommended under every product in the
 * catalog, the classic failure mode of a raw co-occurrence count.
 *
 * Rankings are cached as id => score maps, never as models, so a cached
 * ranking still gets re-checked against live stock when it is hydrated.
 * Nothing out of stock or deactivated is ever recommended.
 */
class Recommender
{
    /** Bump to invalidate every cached ranking after a scoring change. */
    private const CACHE_VERSION = 'v2';

    private const CACHE_TTL = 1800;

    /** Behaviour older than this describes a catalog that no longer exists. */
    private const SIGNAL_WINDOW_DAYS = 180;

    /** Orders that never completed shouldn't shape anyone's recommendations. */
    private const DEAD_ORDER_STATUSES = ['cancelled', 'refunded'];

    /**
     * Cosine floor for calling two products related.
     *
     * Measured, not guessed: across this catalog the pairwise cosines run
     * 0.21 to 0.88 with a median of 0.40, and the bottom quartile sits below
     * 0.35. Products share so much vocabulary ("frame", "lenses", "mm",
     * a price) that nothing ever scores near zero, so the floor has to sit
     * well above it. 0.30 clears the genuinely unrelated tail without
     * cutting into real matches.
     */
    private const MIN_COSINE = 0.30;

    /** Points a perfect semantic match is worth, before behavioural lift. */
    private const SEMANTIC_WEIGHT = 10.0;

    /** Most the co-view signal can add on top of a semantic score. */
    private const CO_VIEW_WEIGHT = 4.0;

    /**
     * Floor for the attribute fallback, out of a possible 18.5 for frames
     * and 16 for lenses — roughly two solid matches. Only reached for
     * products with no stored vector.
     */
    private const MIN_FRAME_SCORE = 4.5;

    private const MIN_LENS_SCORE = 5.0;

    /** How many attribute-scored rows to rank when falling back. */
    private const CANDIDATE_POOL = 60;

    /*
    |--------------------------------------------------------------------------
    | Public API
    |--------------------------------------------------------------------------
    */

    /**
     * "You may also like" — the same catalog as the product being viewed,
     * ranked by semantic similarity and lifted by what co-viewers opened.
     *
     * @return Collection<int, Frame|ContactLens>
     */
    public function similarTo(Frame|ContactLens $product, int $limit = 4): Collection
    {
        return $this->hydrate($this->similarScores($product), $limit, [$this->key($product)]);
    }

    /**
     * "Customers who bought this also bought" — purchase-history
     * collaborative filtering, spanning both catalogs.
     *
     * Returns nothing until the shop has real co-purchase history. That is
     * deliberate: the heading makes a factual claim about other customers,
     * so it must never be filled in with lookalikes.
     *
     * @return Collection<int, Frame|ContactLens>
     */
    public function alsoBought(Frame|ContactLens $product, int $limit = 4): Collection
    {
        return $this->hydrate($this->coPurchaseScores($product), $limit, [$this->key($product)]);
    }

    /**
     * The two rails a product page carries, de-duplicated against each other
     * so the same product never appears in both.
     *
     * "Customers also bought" wins any overlap: it is the stronger claim,
     * and the only one that can be filled from real history.
     *
     * @return array{similar: Collection<int, Frame|ContactLens>, alsoBought: Collection<int, Frame|ContactLens>}
     */
    public function forProductPage(Frame|ContactLens $product, int $limit = 4): array
    {
        $alsoBought = $this->alsoBought($product, $limit);

        $similar = $this->similarTo($product, $limit + $alsoBought->count())
            ->reject(fn (Model $candidate) => $alsoBought->contains(fn (Model $bought) => $bought->is($candidate)))
            ->take($limit)
            ->values();

        return ['similar' => $similar, 'alsoBought' => $alsoBought];
    }

    /**
     * "Recommended for you".
     *
     * Everything the shopper has bought and browsed is averaged into a
     * single taste vector — one point in the same space the products live
     * in, weighted so purchases count for more than views and recent views
     * for more than old ones — and the catalog is then ranked by distance
     * from that point.
     *
     * This is why the rail can cross catalogs coherently: a taste vector
     * built from three frames still has a well-defined distance to every
     * contact lens, so the shopper gets the lenses that suit the way they
     * dress their face, not just the best-selling box.
     *
     * Works for guests too: an unauthenticated shopper's product_views rows
     * are keyed by session id, which is enough to personalize a first visit.
     *
     * @return Collection<int, Frame|ContactLens>
     */
    public function forShopper(?User $user, ?string $sessionId, int $limit = 8): Collection
    {
        $seeds = $this->affinitySeeds($user, $sessionId);

        if ($seeds->isEmpty()) {
            return collect();
        }

        $scores = $this->tasteScores($seeds);

        foreach ($seeds as $seed) {
            // Co-purchase is the only signal that knows a frame buyer tends
            // to come back for lenses, so it gets a say alongside taste.
            $this->accumulate($scores, $this->coPurchaseScores($seed['product']), $seed['weight'] * 1.5);
        }

        $exclude = array_merge(
            $seeds->map(fn (array $seed) => $this->key($seed['product']))->all(),
            $user ? $this->purchasedKeys($user) : [],
        );

        return $this->hydrate($scores, $limit, $exclude);
    }

    /**
     * "You bought X — you might like Y", anchored on one order so the rail
     * can name the reason it is showing what it shows.
     *
     * @return array{seed: Frame|ContactLens, products: Collection<int, Frame|ContactLens>}|null
     */
    public function relatedToOrder(Order $order, int $limit = 4): ?array
    {
        $order->loadMissing(['eyeglasses.frame', 'contactLenses.contactLens', 'user']);

        $bought = $order->eyeglasses
            ->map(fn (OrderEyeglass $line) => $line->frame)
            ->merge($order->contactLenses->map(fn (OrderContactLens $line) => $line->contactLens))
            ->filter()
            ->values();

        if ($bought->isEmpty()) {
            return null;
        }

        $seeds = $bought->map(fn (Frame|ContactLens $p) => ['product' => $p, 'weight' => 1.0]);
        $scores = $this->tasteScores($seeds);

        foreach ($bought as $product) {
            $this->accumulate($scores, $this->coPurchaseScores($product), 1.5);
        }

        $products = $this->hydrate($scores, $limit, $this->purchasedKeys($order->user));

        return $products->isEmpty() ? null : ['seed' => $bought->first(), 'products' => $products];
    }

    /**
     * "Goes well with your cart" — the taste vector of what is in the cart
     * right now, excluding the cart itself.
     *
     * @param  Collection<int, Frame|ContactLens>  $inCart
     * @return Collection<int, Frame|ContactLens>
     */
    public function toCompleteCart(Collection $inCart, int $limit = 4): Collection
    {
        if ($inCart->isEmpty()) {
            return collect();
        }

        $seeds = $inCart->map(fn (Frame|ContactLens $p) => ['product' => $p, 'weight' => 1.0]);

        // Weighted towards co-purchase: someone with a frame in the cart
        // wants the things that go with it, not four more frames.
        $scores = $this->tasteScores($seeds, 0.6);

        foreach ($inCart as $product) {
            $this->accumulate($scores, $this->coPurchaseScores($product), 1.5);
        }

        return $this->hydrate($scores, $limit, $inCart->map(fn (Model $p) => $this->key($p))->all());
    }

    /*
    |--------------------------------------------------------------------------
    | Semantic similarity
    |--------------------------------------------------------------------------
    */

    /**
     * Semantic similarity lifted by co-view, within the product's own
     * catalog. Falls back to attribute scoring when the product has no
     * stored vector — a brand-new product, or an install where
     * `php artisan catalog:embed` has not run yet.
     *
     * @return array<string, float>
     */
    private function similarScores(Frame|ContactLens $product): array
    {
        return Cache::remember(
            $this->cacheKey('similar', $product),
            self::CACHE_TTL,
            function () use ($product) {
                $coView = $this->normalize($this->coViewCounts($product));
                $semantic = $this->semanticScores($product);

                // Fall back only when the semantic path could not run at all
                // — this product has no vector, or nothing in its catalog
                // does. An empty result from a path that *did* run means the
                // model genuinely found nothing close, and that answer has
                // to stand: resurrecting those products through attribute
                // scoring would make MIN_COSINE decorative.
                if ($semantic === null || $this->embeddedCount($product->getMorphClass()) < 2) {
                    return $this->attributeScores($product, $coView);
                }

                $scores = [];

                foreach ($semantic as $key => $points) {
                    [$class, $id] = explode(':', $key);

                    // Same catalog only: "you may also like" sits under a
                    // product, where a box of lenses is not an alternative
                    // to a frame. Crossing catalogs is the job of the
                    // co-purchase rail and the personalized one.
                    if ($class !== $product->getMorphClass()) {
                        continue;
                    }

                    $scores[$key] = $points + ($coView[(int) $id] ?? 0.0) * self::CO_VIEW_WEIGHT;
                }

                // A heavily co-viewed product earns its place even if the
                // model put it below the floor — people comparing two
                // products is evidence the text could not supply.
                foreach ($coView as $id => $lift) {
                    $key = $product->getMorphClass().':'.$id;
                    $scores[$key] ??= $lift * self::CO_VIEW_WEIGHT;
                }

                return $scores;
            }
        );
    }

    /**
     * Cosine of the seed against every other embedded product, rescaled to
     * points.
     *
     * The rescale is anchored at MIN_COSINE rather than at the weakest
     * candidate, so the points mean the same thing for a tight cluster and
     * a loose one. Contact-lens descriptions share far more vocabulary than
     * frame descriptions and sit at systematically higher cosines; min-max
     * over the candidates alone would quietly award a lens rail full marks
     * for what is really an average match.
     *
     * Returns null — distinct from an empty array — when this product has no
     * stored vector, so callers can tell "nothing is close" apart from
     * "there was nothing to measure".
     *
     * @return array<string, float>|null
     */
    private function semanticScores(Frame|ContactLens $product): ?array
    {
        $matrix = $this->embeddingMatrix();
        $seed = $matrix[$this->key($product)] ?? null;

        if ($seed === null) {
            return null;
        }

        $cosines = [];

        foreach ($matrix as $key => $vector) {
            if ($key === $this->key($product)) {
                continue;
            }

            $cosine = $this->dot($seed, $vector);

            if ($cosine >= self::MIN_COSINE) {
                $cosines[$key] = $cosine;
            }
        }

        return $this->toPoints($cosines);
    }

    /**
     * Rank the whole catalog against a shopper's taste vector.
     *
     * @param  Collection<int, array{product: Frame|ContactLens, weight: float}>  $seeds
     * @return array<string, float>
     */
    private function tasteScores(Collection $seeds, float $weight = 1.0): array
    {
        $taste = $this->tasteVector($seeds);

        if ($taste === null) {
            // No vectors anywhere — fall back to per-seed attribute scoring.
            $scores = [];

            foreach ($seeds as $seed) {
                $this->accumulate($scores, $this->similarScores($seed['product']), $seed['weight'] * $weight);
            }

            return $scores;
        }

        $cosines = [];

        foreach ($this->embeddingMatrix() as $key => $vector) {
            $cosine = $this->dot($taste, $vector);

            if ($cosine >= self::MIN_COSINE) {
                $cosines[$key] = $cosine;
            }
        }

        return array_map(fn (float $points) => $points * $weight, $this->toPoints($cosines));
    }

    /**
     * The weighted centroid of the seeds' vectors, re-normalized to unit
     * length so its dot products stay true cosines.
     *
     * Averaging embeddings is a crude summary of a person — someone buying
     * for themselves and for a child lands between the two — but it is the
     * standard one, and with a handful of seeds it holds up.
     *
     * @param  Collection<int, array{product: Frame|ContactLens, weight: float}>  $seeds
     * @return array<int, float>|null
     */
    private function tasteVector(Collection $seeds): ?array
    {
        $matrix = $this->embeddingMatrix();
        $sum = null;
        $total = 0.0;

        foreach ($seeds as $seed) {
            $vector = $matrix[$this->key($seed['product'])] ?? null;

            if ($vector === null) {
                continue;
            }

            $weight = (float) $seed['weight'];
            $total += $weight;

            if ($sum === null) {
                $sum = array_fill(0, count($vector), 0.0);
            }

            foreach ($vector as $i => $value) {
                $sum[$i] += $value * $weight;
            }
        }

        if ($sum === null || $total <= 0.0) {
            return null;
        }

        $length = sqrt(array_sum(array_map(fn (float $v) => $v * $v, $sum)));

        if ($length <= 1e-9) {
            return null;
        }

        return array_map(fn (float $v) => $v / $length, $sum);
    }

    /**
     * Every stored vector, keyed by "Class:id".
     *
     * Loaded once and cached: at catalog scale this is a few hundred
     * kilobytes and turns every similarity into arithmetic on an array
     * rather than a query. A catalog large enough for this to hurt (call it
     * six figures) is the point where the vectors belong in a dedicated
     * index instead, not the point where this file grows a query planner.
     *
     * @return array<string, array<int, float>>
     */
    private function embeddingMatrix(): array
    {
        return Cache::remember(CatalogEmbedder::CACHE_KEY, self::CACHE_TTL, function () {
            $matrix = [];

            foreach (ProductEmbedding::where('model', CatalogEmbedder::MODEL)->cursor() as $row) {
                $matrix[$row->embeddable_type.':'.$row->embeddable_id] = $row->toVector();
            }

            return $matrix;
        });
    }

    /** How many products of one class currently have a usable vector. */
    private function embeddedCount(string $class): int
    {
        $prefix = $class.':';

        return count(array_filter(
            array_keys($this->embeddingMatrix()),
            fn (string $key) => str_starts_with($key, $prefix),
        ));
    }

    /**
     * Rescale cosines above the floor onto a 0..SEMANTIC_WEIGHT scale.
     *
     * @param  array<string, float>  $cosines
     * @return array<string, float>
     */
    private function toPoints(array $cosines): array
    {
        if ($cosines === []) {
            return [];
        }

        $ceiling = max(max($cosines), self::MIN_COSINE + 0.05);
        $span = $ceiling - self::MIN_COSINE;

        return array_map(
            fn (float $cosine) => (($cosine - self::MIN_COSINE) / $span) * self::SEMANTIC_WEIGHT,
            $cosines,
        );
    }

    /** Cosine similarity, given both vectors are stored unit length. */
    private function dot(array $a, array $b): float
    {
        $sum = 0.0;

        foreach ($a as $i => $value) {
            $sum += $value * ($b[$i] ?? 0.0);
        }

        return $sum;
    }

    /*
    |--------------------------------------------------------------------------
    | Attribute fallback
    |--------------------------------------------------------------------------
    |
    | Used only for products with no stored vector. Keeping it means a fresh
    | clone that has not run `catalog:embed` — or a frame added through the
    | admin area an hour ago — still gets recommendations, just from the
    | cheaper signal.
    |
    */

    /**
     * @param  array<int, float>  $coView
     * @return array<string, float>
     */
    private function attributeScores(Frame|ContactLens $product, array $coView): array
    {
        $query = $product instanceof Frame
            ? $this->frameScoreQuery($product)
            : $this->contactLensScoreQuery($product);

        $candidates = (clone $query)
            ->orderByDesc('match_score')
            ->limit(self::CANDIDATE_POOL)
            ->get();

        $missing = array_diff(array_keys($coView), $candidates->modelKeys());

        if ($missing !== []) {
            $candidates = $candidates->merge((clone $query)->whereKey($missing)->get());
        }

        $floor = $product instanceof Frame ? self::MIN_FRAME_SCORE : self::MIN_LENS_SCORE;
        $scores = [];

        foreach ($candidates as $candidate) {
            $content = (float) $candidate->match_score;
            $lift = ($coView[$candidate->getKey()] ?? 0.0) * self::CO_VIEW_WEIGHT;

            if ($content < $floor && $lift <= 0.0) {
                continue;
            }

            $scores[$this->key($candidate)] = $content + $lift;
        }

        return $scores;
    }

    private function frameScoreQuery(Frame $seed): Builder
    {
        $price = (float) $seed->price;
        $band = max($price * 0.5, 30.0);

        $expression = implode(' + ', [
            '(CASE WHEN shape = ? THEN 3 ELSE 0 END)',
            '(CASE WHEN category = ? THEN 3 ELSE 0 END)',
            '(CASE WHEN type = ? THEN 2 ELSE 0 END)',
            '(CASE WHEN material = ? THEN 2 ELSE 0 END)',
            "(CASE WHEN gender = ? THEN 2 WHEN gender = 'unisex' OR ? = 'unisex' THEN 1 ELSE 0 END)",
            '(CASE WHEN brand = ? THEN 1.5 ELSE 0 END)',
            '(CASE WHEN size = ? THEN 1.5 ELSE 0 END)',
            '(CASE WHEN color = ? THEN 1 ELSE 0 END)',
            '(CASE WHEN ABS(price - ?) < ? THEN 2.5 * (1 - ABS(price - ?) / ?) ELSE 0 END)',
        ]);

        return Frame::query()
            ->selectRaw("frames.*, ({$expression}) as match_score", [
                $seed->shape, $seed->category, $seed->type, $seed->material,
                $seed->gender, $seed->gender, $seed->brand, $seed->size, $seed->color,
                $price, $band, $price, $band,
            ])
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereKeyNot($seed->getKey());
    }

    private function contactLensScoreQuery(ContactLens $seed): Builder
    {
        $unitPrice = '(price / (CASE WHEN pack_size > 0 THEN pack_size ELSE 1 END))';
        $seedUnit = (float) $seed->price / max(1, (int) $seed->pack_size);
        $band = max($seedUnit * 0.5, 1.0);

        $expression = implode(' + ', [
            '(CASE WHEN brand = ? THEN 3 ELSE 0 END)',
            '(CASE WHEN type = ? THEN 3 ELSE 0 END)',
            '(CASE WHEN material = ? THEN 2 ELSE 0 END)',
            '(CASE WHEN ABS(base_curve - ?) <= 0.2 THEN 2 ELSE 0 END)',
            '(CASE WHEN ABS(diameter - ?) <= 0.3 THEN 1.5 ELSE 0 END)',
            '(CASE WHEN color = ? THEN 1.5 WHEN color IS NULL AND ? IS NULL THEN 1 ELSE 0 END)',
            '(CASE WHEN pack_size = ? THEN 1 ELSE 0 END)',
            "(CASE WHEN ABS({$unitPrice} - ?) < ? THEN 2 * (1 - ABS({$unitPrice} - ?) / ?) ELSE 0 END)",
        ]);

        return ContactLens::query()
            ->selectRaw("contact_lenses.*, ({$expression}) as match_score", [
                $seed->brand, $seed->type, $seed->material, $seed->base_curve,
                $seed->diameter, $seed->color, $seed->color, $seed->pack_size,
                $seedUnit, $band, $seedUnit, $band,
            ])
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereKeyNot($seed->getKey());
    }

    /*
    |--------------------------------------------------------------------------
    | Behavioural signals
    |--------------------------------------------------------------------------
    */

    /**
     * Customers who bought this also bought — across both catalogs.
     *
     * @return array<string, float>
     */
    private function coPurchaseScores(Frame|ContactLens $product): array
    {
        return Cache::remember(
            $this->cacheKey('co-purchase', $product),
            self::CACHE_TTL,
            fn () => $this->dampen(Frame::class, $this->coPurchasedFrames($product))
                + $this->dampen(ContactLens::class, $this->coPurchasedContactLenses($product))
        );
    }

    /**
     * Products viewed by the same people who viewed this one, counted as
     * distinct visitors rather than page hits so one obsessive browser can't
     * manufacture a recommendation on their own.
     *
     * A visitor is a user id when signed in and a session id otherwise;
     * product_views only ever sets one of the two, so summing the two
     * distinct counts is exactly the number of distinct visitors.
     *
     * @return array<int, float> candidate id => visitor count
     */
    private function coViewCounts(Frame|ContactLens $product): array
    {
        $type = $product->getMorphClass();
        $since = now()->subDays(self::SIGNAL_WINDOW_DAYS);

        return DB::table('product_views as v1')
            ->join('product_views as v2', fn ($join) => $join->whereRaw(
                '((v1.user_id IS NOT NULL AND v1.user_id = v2.user_id)'
                .' OR (v1.session_id IS NOT NULL AND v1.session_id = v2.session_id))'
            ))
            ->where('v1.viewable_type', $type)
            ->where('v1.viewable_id', $product->getKey())
            ->where('v2.viewable_type', $type)
            ->where('v2.viewable_id', '!=', $product->getKey())
            ->where('v1.created_at', '>=', $since)
            ->where('v2.created_at', '>=', $since)
            ->groupBy('v2.viewable_id')
            ->selectRaw('v2.viewable_id as id, COUNT(DISTINCT v2.user_id) + COUNT(DISTINCT v2.session_id) as visitors')
            ->pluck('visitors', 'id')
            ->map(fn ($visitors) => (float) $visitors)
            ->all();
    }

    /**
     * Frames bought by the customers who bought this product.
     *
     * Co-occurrence is measured per customer, not per basket: an optician's
     * customer buys one frame and comes back months later for the next
     * thing, so basket-level co-occurrence would be empty almost every time.
     *
     * @return array<int, float> frame id => buyer count
     */
    private function coPurchasedFrames(Frame|ContactLens $product): array
    {
        return DB::table('order_eyeglasses as line')
            ->join('orders as o', 'o.id', '=', 'line.order_id')
            ->whereNotIn('o.status', self::DEAD_ORDER_STATUSES)
            ->whereNotNull('line.frame_id')
            ->when($product instanceof Frame, fn ($q) => $q->where('line.frame_id', '!=', $product->getKey()))
            ->whereIn('o.user_id', $this->buyersOf($product))
            ->groupBy('line.frame_id')
            ->selectRaw('line.frame_id as id, COUNT(DISTINCT o.user_id) as buyers')
            ->pluck('buyers', 'id')
            ->map(fn ($buyers) => (float) $buyers)
            ->all();
    }

    /**
     * Contact lenses bought by the customers who bought this product.
     *
     * @return array<int, float> contact lens id => buyer count
     */
    private function coPurchasedContactLenses(Frame|ContactLens $product): array
    {
        return DB::table('order_contact_lenses as line')
            ->join('orders as o', 'o.id', '=', 'line.order_id')
            ->whereNotIn('o.status', self::DEAD_ORDER_STATUSES)
            ->whereNotNull('line.contact_lens_id')
            ->when($product instanceof ContactLens, fn ($q) => $q->where('line.contact_lens_id', '!=', $product->getKey()))
            ->whereIn('o.user_id', $this->buyersOf($product))
            ->groupBy('line.contact_lens_id')
            ->selectRaw('line.contact_lens_id as id, COUNT(DISTINCT o.user_id) as buyers')
            ->pluck('buyers', 'id')
            ->map(fn ($buyers) => (float) $buyers)
            ->all();
    }

    /**
     * Sub-select of the user ids who have bought this product, kept as a
     * closure so the id list never has to be pulled into PHP.
     */
    private function buyersOf(Frame|ContactLens $product): Closure
    {
        return function ($query) use ($product) {
            $table = $product instanceof Frame ? 'order_eyeglasses' : 'order_contact_lenses';
            $column = $product instanceof Frame ? 'frame_id' : 'contact_lens_id';

            $query->select('orders.user_id')
                ->from($table)
                ->join('orders', 'orders.id', '=', $table.'.order_id')
                ->where($table.'.'.$column, $product->getKey())
                ->whereNotIn('orders.status', self::DEAD_ORDER_STATUSES);
        };
    }

    /**
     * Divide each co-occurrence count by the square root of the candidate's
     * total audience. Without this the shop's best-seller co-occurs with
     * everything and gets recommended everywhere; with it, a product only
     * ranks when it co-occurs with *this* item more than its own popularity
     * already explains.
     *
     * @param  array<int, float>  $counts
     * @return array<string, float>
     */
    private function dampen(string $class, array $counts): array
    {
        if ($counts === []) {
            return [];
        }

        $popularity = $this->buyerCounts($class, array_keys($counts));
        $scores = [];

        foreach ($counts as $id => $count) {
            $total = max(1.0, $popularity[$id] ?? 1.0);
            $scores[$class.':'.$id] = $count / sqrt($total);
        }

        return $scores;
    }

    /**
     * Total distinct buyers per product, for the popularity damping above.
     *
     * @param  array<int, int|string>  $ids
     * @return array<int, float>
     */
    private function buyerCounts(string $class, array $ids): array
    {
        [$table, $column] = $class === Frame::class
            ? ['order_eyeglasses', 'frame_id']
            : ['order_contact_lenses', 'contact_lens_id'];

        return DB::table($table.' as line')
            ->join('orders as o', 'o.id', '=', 'line.order_id')
            ->whereNotIn('o.status', self::DEAD_ORDER_STATUSES)
            ->whereIn('line.'.$column, $ids)
            ->groupBy('line.'.$column)
            ->selectRaw('line.'.$column.' as id, COUNT(DISTINCT o.user_id) as buyers')
            ->pluck('buyers', 'id')
            ->map(fn ($buyers) => (float) $buyers)
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Shopper affinity
    |--------------------------------------------------------------------------
    */

    /**
     * The handful of products that best describe what this shopper is into:
     * their latest purchases first, then what they have been looking at,
     * with older views counting for less.
     *
     * @return Collection<int, array{product: Frame|ContactLens, weight: float}>
     */
    private function affinitySeeds(?User $user, ?string $sessionId): Collection
    {
        $seeds = collect();

        if ($user) {
            foreach ($this->recentPurchases($user, 3) as $product) {
                $seeds->push(['product' => $product, 'weight' => 3.0]);
            }
        }

        if ($user || $sessionId) {
            $views = ProductView::query()
                ->when(
                    $user,
                    fn ($q) => $q->where('user_id', $user->id),
                    fn ($q) => $q->where('session_id', $sessionId)
                )
                ->where('created_at', '>=', now()->subDays(self::SIGNAL_WINDOW_DAYS))
                ->latest('created_at')
                ->limit(40)
                ->get()
                ->unique(fn (ProductView $view) => $view->viewable_type.':'.$view->viewable_id)
                ->take(5)
                ->values();

            foreach ($views as $index => $view) {
                $product = $view->viewable;

                if (! $product instanceof Frame && ! $product instanceof ContactLens) {
                    continue;
                }

                $seeds->push(['product' => $product, 'weight' => max(0.6, 1.5 - 0.2 * $index)]);
            }
        }

        // De-duplicate — a bought product is usually a viewed one too —
        // keeping the heavier weight, then cap the seeds so rendering a
        // homepage stays a bounded amount of work.
        return $seeds
            ->sortByDesc('weight')
            ->unique(fn (array $seed) => $this->key($seed['product']))
            ->take(5)
            ->values();
    }

    /**
     * The shopper's most recently purchased products, newest first.
     *
     * @return Collection<int, Frame|ContactLens>
     */
    private function recentPurchases(User $user, int $limit): Collection
    {
        $lines = collect();

        foreach ([
            [Frame::class, 'order_eyeglasses', 'frame_id'],
            [ContactLens::class, 'order_contact_lenses', 'contact_lens_id'],
        ] as [$class, $table, $column]) {
            $rows = DB::table($table.' as line')
                ->join('orders as o', 'o.id', '=', 'line.order_id')
                ->where('o.user_id', $user->id)
                ->whereNotIn('o.status', self::DEAD_ORDER_STATUSES)
                ->whereNotNull('line.'.$column)
                ->orderByDesc('o.created_at')
                ->limit($limit)
                ->get(['line.'.$column.' as id', 'o.created_at as ordered_at']);

            foreach ($rows as $row) {
                $lines->push(['class' => $class, 'id' => (int) $row->id, 'at' => $row->ordered_at]);
            }
        }

        $lines = $lines
            ->sortByDesc('at')
            ->unique(fn (array $line) => $line['class'].':'.$line['id'])
            ->take($limit)
            ->values();

        $products = $this->load(
            $lines->where('class', Frame::class)->pluck('id')->all(),
            $lines->where('class', ContactLens::class)->pluck('id')->all(),
        );

        // Restore purchase order, dropping anything since sold out or removed.
        return $lines
            ->map(fn (array $line) => $products[$line['class'].':'.$line['id']] ?? null)
            ->filter()
            ->values();
    }

    /**
     * Everything the shopper already owns — never worth recommending back.
     *
     * @return array<int, string>
     */
    private function purchasedKeys(User $user): array
    {
        $frames = DB::table('order_eyeglasses as line')
            ->join('orders as o', 'o.id', '=', 'line.order_id')
            ->where('o.user_id', $user->id)
            ->whereNotNull('line.frame_id')
            ->pluck('line.frame_id')
            ->map(fn ($id) => Frame::class.':'.$id);

        $lenses = DB::table('order_contact_lenses as line')
            ->join('orders as o', 'o.id', '=', 'line.order_id')
            ->where('o.user_id', $user->id)
            ->whereNotNull('line.contact_lens_id')
            ->pluck('line.contact_lens_id')
            ->map(fn ($id) => ContactLens::class.':'.$id);

        return $frames->merge($lenses)->unique()->values()->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Ranking helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Turn a score map into real, currently-buyable products, highest first.
     *
     * Availability is re-checked here rather than at scoring time, so a
     * cached ranking can never surface something that has since sold out or
     * been deactivated.
     *
     * @param  array<string, float>  $scores
     * @param  array<int, string>  $exclude
     * @return Collection<int, Frame|ContactLens>
     */
    private function hydrate(array $scores, int $limit, array $exclude = []): Collection
    {
        $scores = array_diff_key($scores, array_flip($exclude));

        if ($scores === []) {
            return collect();
        }

        arsort($scores);

        // Over-fetch: some top-scoring ids get filtered out by the
        // availability checks in load(), and we still want a full rail.
        $shortlist = array_slice($scores, 0, max($limit * 4, 20), true);

        $ids = [Frame::class => [], ContactLens::class => []];

        foreach (array_keys($shortlist) as $key) {
            [$class, $id] = explode(':', $key);
            $ids[$class][] = (int) $id;
        }

        $products = $this->load($ids[Frame::class], $ids[ContactLens::class]);

        return collect($shortlist)
            ->keys()
            ->map(fn (string $key) => $products[$key] ?? null)
            ->filter()
            ->take($limit)
            ->values();
    }

    /**
     * Load the given frames and lenses with everything the product cards
     * render, keyed by "Class:id".
     *
     * @param  array<int, int>  $frameIds
     * @param  array<int, int>  $lensIds
     * @return array<string, Frame|ContactLens>
     */
    private function load(array $frameIds, array $lensIds): array
    {
        $products = [];

        if ($frameIds !== []) {
            $frames = Frame::query()
                ->whereKey($frameIds)
                ->where('is_active', true)
                ->where('stock', '>', 0)
                ->with('primaryImage')
                ->withAvg('approvedReviews as reviews_avg_rating', 'rating')
                ->withCount('approvedReviews as reviews_count')
                ->get();

            foreach ($frames as $frame) {
                $products[$this->key($frame)] = $frame;
            }
        }

        if ($lensIds !== []) {
            $lenses = ContactLens::query()
                ->whereKey($lensIds)
                ->where('is_active', true)
                ->where('stock', '>', 0)
                ->withAvg('approvedReviews as reviews_avg_rating', 'rating')
                ->withCount('approvedReviews as reviews_count')
                ->get();

            foreach ($lenses as $lens) {
                $products[$this->key($lens)] = $lens;
            }
        }

        return $products;
    }

    /**
     * Add a weighted score map into an accumulator.
     *
     * @param  array<string, float>  $into
     * @param  array<string, float>  $scores
     */
    private function accumulate(array &$into, array $scores, float $weight): void
    {
        foreach ($scores as $key => $score) {
            $into[$key] = ($into[$key] ?? 0.0) + $score * $weight;
        }
    }

    /**
     * Scale a raw count map to 0..1 so signals measured in different units
     * (visitors, buyers, cosine points) can be added together.
     *
     * @param  array<int|string, float>  $values
     * @return array<int|string, float>
     */
    private function normalize(array $values): array
    {
        $max = $values === [] ? 0.0 : max($values);

        if ($max <= 0.0) {
            return [];
        }

        return array_map(fn (float $value) => $value / $max, $values);
    }

    private function key(Model $product): string
    {
        return $product->getMorphClass().':'.$product->getKey();
    }

    private function cacheKey(string $signal, Model $product): string
    {
        return 'rec:'.self::CACHE_VERSION.':'.$signal.':'.str_replace('\\', '.', $this->key($product));
    }
}
