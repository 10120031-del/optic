<?php

namespace App\Services;

use App\Models\ContactLens;
use App\Models\Frame;
use App\Models\Order;
use App\Models\OrderContactLens;
use App\Models\OrderEyeglass;
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
 * Three signals, blended, none of which needed a new table — the catalog
 * columns, the order lines and the product_views log the shop already keeps
 * are the whole input:
 *
 *   1. Content similarity. Every catalog attribute a shopper actually chooses
 *      on (a frame's outline shape and what it's for, a lens's replacement
 *      schedule and base curve) scored as a weighted match against the item
 *      in front of them. This is the part that works on day one, with an
 *      empty orders table and no traffic.
 *   2. Co-view. People who looked at this also looked at that, from
 *      product_views. Picks up taste the attribute columns can't name — the
 *      reason two unrelated-on-paper frames keep getting compared.
 *   3. Co-purchase. Customers who bought this also bought that, measured
 *      across a customer's whole history rather than within one order,
 *      because nobody buys two pairs of glasses in a single checkout.
 *      Deliberately cross-catalog: buying a frame is the best predictor
 *      there is that someone also wants contact lenses.
 *
 * Both collaborative signals are damped by the candidate's own popularity
 * (divided by the square root of its total audience), so the shop's
 * best-seller doesn't end up recommended underneath every product in the
 * catalog — the classic failure mode of a raw co-occurrence count.
 *
 * Rankings are cached as id => score maps, never as models, so a cached
 * ranking still gets re-checked against live stock when it is hydrated.
 * Nothing out of stock or deactivated is ever recommended.
 */
class Recommender
{
    /** Bump to invalidate every cached ranking after a scoring change. */
    private const CACHE_VERSION = 'v1';

    private const CACHE_TTL = 1800;

    /** Behaviour older than this describes a catalog that no longer exists. */
    private const SIGNAL_WINDOW_DAYS = 180;

    /** Orders that never completed shouldn't shape anyone's recommendations. */
    private const DEAD_ORDER_STATUSES = ['cancelled', 'refunded'];

    /** How many content-scored rows to rank before blending in co-views. */
    private const CANDIDATE_POOL = 60;

    /**
     * Floor for "you may also like" on pure attribute matching, out of a
     * possible 18.5 for frames and 16 for lenses — roughly two solid
     * matches. Below this the catalog holds nothing genuinely similar, and
     * an empty rail beats one padded with filler.
     */
    private const MIN_FRAME_SCORE = 4.5;

    private const MIN_LENS_SCORE = 5.0;

    /** Most the co-view signal can add on top of a content score. */
    private const CO_VIEW_WEIGHT = 4.0;

    /*
    |--------------------------------------------------------------------------
    | Public API
    |--------------------------------------------------------------------------
    */

    /**
     * "You may also like" — the same catalog as the product being viewed,
     * ranked by attribute similarity and lifted by what co-viewers opened.
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
     * so it must never be filled in with attribute lookalikes.
     *
     * @return Collection<int, Frame|ContactLens>
     */
    public function alsoBought(Frame|ContactLens $product, int $limit = 4): Collection
    {
        return $this->hydrate($this->coPurchaseScores($product), $limit, [$this->key($product)]);
    }

    /**
     * "Recommended for you" — a blend over everything the shopper has bought
     * and browsed. Purchases outrank views, recent views outrank older ones.
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

        $scores = [];

        foreach ($seeds as $seed) {
            $product = $seed['product'];
            $weight = $seed['weight'];

            $this->accumulate($scores, $this->similarScores($product), $weight);

            // Co-purchase is the stronger evidence wherever it exists, and the
            // only signal that can carry a frame buyer across to the
            // contact-lens catalog.
            $this->accumulate($scores, $this->coPurchaseScores($product), $weight * 1.5);
        }

        $exclude = array_merge(
            $seeds->map(fn (array $seed) => $this->key($seed['product']))->all(),
            $user ? $this->purchasedKeys($user) : [],
        );

        return $this->hydrate($scores, $limit, $exclude);
    }

    /**
     * The two rails a product page carries, de-duplicated against each other
     * so the same frame never appears in both.
     *
     * "Customers also bought" wins any overlap: it is the stronger claim, and
     * it is the one that can only be filled from real history.
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

        $scores = [];

        foreach ($bought as $product) {
            $this->accumulate($scores, $this->coPurchaseScores($product), 1.5);
            $this->accumulate($scores, $this->similarScores($product), 1.0);
        }

        $products = $this->hydrate($scores, $limit, $this->purchasedKeys($order->user));

        return $products->isEmpty() ? null : ['seed' => $bought->first(), 'products' => $products];
    }

    /**
     * "Goes well with your cart" — seeded by what is in the cart right now
     * and excluding it, so the rail never suggests something already added.
     *
     * @param  Collection<int, Frame|ContactLens>  $inCart
     * @return Collection<int, Frame|ContactLens>
     */
    public function toCompleteCart(Collection $inCart, int $limit = 4): Collection
    {
        $scores = [];

        foreach ($inCart as $product) {
            // Weighted towards co-purchase: someone with a frame in the cart
            // wants the things that go with it, not four more frames.
            $this->accumulate($scores, $this->coPurchaseScores($product), 1.5);
            $this->accumulate($scores, $this->similarScores($product), 0.6);
        }

        return $this->hydrate($scores, $limit, $inCart->map(fn (Model $p) => $this->key($p))->all());
    }

    /*
    |--------------------------------------------------------------------------
    | Scoring — cached id => score maps
    |--------------------------------------------------------------------------
    */

    /**
     * Content similarity lifted by co-view, within the product's own catalog.
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

                $query = $product instanceof Frame
                    ? $this->frameScoreQuery($product)
                    : $this->contactLensScoreQuery($product);

                $candidates = (clone $query)
                    ->orderByDesc('match_score')
                    ->limit(self::CANDIDATE_POOL)
                    ->get();

                // A heavily co-viewed product earns its place even if it fell
                // outside the content pool, so pull in whatever the pool missed.
                $missing = array_diff(array_keys($coView), $candidates->modelKeys());

                if ($missing !== []) {
                    $candidates = $candidates->merge((clone $query)->whereKey($missing)->get());
                }

                $floor = $product instanceof Frame ? self::MIN_FRAME_SCORE : self::MIN_LENS_SCORE;
                $scores = [];

                foreach ($candidates as $candidate) {
                    $content = (float) $candidate->match_score;
                    $lift = ($coView[$candidate->getKey()] ?? 0.0) * self::CO_VIEW_WEIGHT;

                    // Either a real attribute match, or hard evidence that
                    // shoppers compare the two. Neither one alone is filler.
                    if ($content < $floor && $lift <= 0.0) {
                        continue;
                    }

                    $scores[$this->key($candidate)] = $content + $lift;
                }

                return $scores;
            }
        );
    }

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

    /*
    |--------------------------------------------------------------------------
    | Content similarity
    |--------------------------------------------------------------------------
    */

    /**
     * Frames scored against a seed frame, weighted by how much each attribute
     * actually drives the choice: outline shape and what the frame is *for*
     * dominate, brand and colour are tie-breakers, and price proximity keeps
     * the rail inside the budget the shopper has already shown us.
     *
     * A null attribute on either side simply scores zero — `col = NULL` is
     * never true — so partly-filled catalog rows degrade instead of breaking.
     */
    private function frameScoreQuery(Frame $seed): Builder
    {
        $price = (float) $seed->price;

        // Half the seed's price, floored at $30, so the band stays meaningful
        // for a budget frame without swallowing the catalog for a premium one.
        $band = max($price * 0.5, 30.0);

        $expression = implode(' + ', [
            "(CASE WHEN shape = ? THEN 3 ELSE 0 END)",
            "(CASE WHEN category = ? THEN 3 ELSE 0 END)",
            "(CASE WHEN type = ? THEN 2 ELSE 0 END)",
            "(CASE WHEN material = ? THEN 2 ELSE 0 END)",
            "(CASE WHEN gender = ? THEN 2 WHEN gender = 'unisex' OR ? = 'unisex' THEN 1 ELSE 0 END)",
            "(CASE WHEN brand = ? THEN 1.5 ELSE 0 END)",
            "(CASE WHEN size = ? THEN 1.5 ELSE 0 END)",
            "(CASE WHEN color = ? THEN 1 ELSE 0 END)",
            "(CASE WHEN ABS(price - ?) < ? THEN 2.5 * (1 - ABS(price - ?) / ?) ELSE 0 END)",
        ]);

        return Frame::query()
            ->selectRaw("frames.*, ({$expression}) as match_score", [
                $seed->shape,
                $seed->category,
                $seed->type,
                $seed->material,
                $seed->gender,
                $seed->gender,
                $seed->brand,
                $seed->size,
                $seed->color,
                $price, $band, $price, $band,
            ])
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereKeyNot($seed->getKey());
    }

    /**
     * Contact lenses scored against a seed lens. Replacement schedule and
     * brand carry the most weight because that is how wearers shop, and base
     * curve / diameter are scored as tolerances rather than exact matches —
     * they are fit measurements, not labels.
     */
    private function contactLensScoreQuery(ContactLens $seed): Builder
    {
        // Boxes come in wildly different pack sizes, so compare what a wearer
        // pays per lens rather than per box.
        $unitPrice = '(price / (CASE WHEN pack_size > 0 THEN pack_size ELSE 1 END))';

        $seedUnit = (float) $seed->price / max(1, (int) $seed->pack_size);
        $band = max($seedUnit * 0.5, 1.0);

        $expression = implode(' + ', [
            "(CASE WHEN brand = ? THEN 3 ELSE 0 END)",
            "(CASE WHEN type = ? THEN 3 ELSE 0 END)",
            "(CASE WHEN material = ? THEN 2 ELSE 0 END)",
            "(CASE WHEN ABS(base_curve - ?) <= 0.2 THEN 2 ELSE 0 END)",
            "(CASE WHEN ABS(diameter - ?) <= 0.3 THEN 1.5 ELSE 0 END)",
            // Two clear lenses match each other; two coloured lenses only
            // match on the same colour.
            "(CASE WHEN color = ? THEN 1.5 WHEN color IS NULL AND ? IS NULL THEN 1 ELSE 0 END)",
            "(CASE WHEN pack_size = ? THEN 1 ELSE 0 END)",
            "(CASE WHEN ABS({$unitPrice} - ?) < ? THEN 2 * (1 - ABS({$unitPrice} - ?) / ?) ELSE 0 END)",
        ]);

        return ContactLens::query()
            ->selectRaw("contact_lenses.*, ({$expression}) as match_score", [
                $seed->brand,
                $seed->type,
                $seed->material,
                $seed->base_curve,
                $seed->diameter,
                $seed->color,
                $seed->color,
                $seed->pack_size,
                $seedUnit, $band, $seedUnit, $band,
            ])
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereKeyNot($seed->getKey());
    }

    /*
    |--------------------------------------------------------------------------
    | Collaborative signals
    |--------------------------------------------------------------------------
    */

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
     * customer buys one frame and comes back months later for the next thing,
     * so basket-level co-occurrence would be empty almost every time.
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
     * their latest purchases first, then what they have been looking at, with
     * older views counting for less.
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

        // De-duplicate — a bought product is usually a viewed one too — keeping
        // the heavier weight, then cap the seeds so rendering a homepage stays
        // a bounded amount of work.
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
     * (visitors, buyers, attribute points) can be added together.
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
