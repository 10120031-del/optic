<?php

namespace Tests\Unit;

use App\Models\ContactLens;
use App\Models\Frame;
use App\Services\CatalogEmbedder;
use App\Services\Recommender;
use Closure;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The arithmetic behind a recommendation, tested on its own.
 *
 * tests/Feature/RecommenderTest.php already proves the rails come out right
 * end to end; what it cannot show is *why* a product placed where it did.
 * These tests drive the scoring helpers directly with hand-built vectors and
 * score maps, so a failure names the step that broke — the cosine floor, the
 * rescale, the centroid — instead of "the rail is empty".
 *
 * Nothing here touches the database. The one collaborator that would,
 * embeddingMatrix(), reads through the cache, so the tests seed the cache and
 * the query never runs.
 */
class RecommenderScoringTest extends TestCase
{
    private Recommender $rec;

    /** Unit-length vectors, deliberately trivial so the expected math is legible. */
    private const MATRIX = [
        'App\Models\Frame:1' => [1.0, 0.0, 0.0],
        'App\Models\Frame:2' => [0.8, 0.6, 0.0],
        'App\Models\Frame:3' => [0.0, 1.0, 0.0],
        'App\Models\ContactLens:1' => [0.0, 0.0, 1.0],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->rec = new Recommender;
    }

    /**
     * The scoring helpers are private on purpose — they are not API, and
     * testing them through the public methods would mean a database, an
     * embedded catalog and an order history just to check a division. A
     * bound closure reaches them without loosening the class.
     */
    private function callPrivate(string $method, mixed ...$args): mixed
    {
        return Closure::bind(
            fn () => $this->{$method}(...$args), $this->rec, Recommender::class
        )();
    }

    /** accumulate() takes its accumulator by reference, so it needs its own closure. */
    private function accumulator(): Closure
    {
        return Closure::bind(
            function (array &$into, array $scores, float $weight) {
                $this->accumulate($into, $scores, $weight);
            }, $this->rec, Recommender::class
        );
    }

    private function seedMatrix(array $matrix = self::MATRIX): void
    {
        Cache::put(CatalogEmbedder::CACHE_KEY, $matrix, 60);
    }

    private function frame(int $id): Frame
    {
        $frame = new Frame;
        $frame->id = $id;

        return $frame;
    }

    private function affinity(int $id, float $weight): array
    {
        return ['product' => $this->frame($id), 'weight' => $weight];
    }

    /*
    | dot() — cosine similarity, given both vectors are stored unit length.
    */

    #[Test]
    public function a_vector_is_perfectly_similar_to_itself(): void
    {
        $this->assertEqualsWithDelta(1.0, $this->callPrivate('dot', [0.8, 0.6, 0.0], [0.8, 0.6, 0.0]), 1e-9);
    }

    #[Test]
    public function perpendicular_vectors_score_zero_and_opposite_ones_score_minus_one(): void
    {
        $this->assertEqualsWithDelta(0.0, $this->callPrivate('dot', [1.0, 0.0, 0.0], [0.0, 1.0, 0.0]), 1e-9);
        $this->assertEqualsWithDelta(-1.0, $this->callPrivate('dot', [1.0, 0.0, 0.0], [-1.0, 0.0, 0.0]), 1e-9);
    }

    #[Test]
    public function a_short_vector_is_padded_with_zeros_rather_than_erroring(): void
    {
        // A vector stored at an older dimensionality must not fatal the rail.
        $this->assertEqualsWithDelta(0.8, $this->callPrivate('dot', [0.8, 0.6, 0.0], [1.0]), 1e-9);
    }

    /*
    | toPoints() — rescale cosines above the floor onto 0..SEMANTIC_WEIGHT.
    */

    #[Test]
    public function the_best_match_takes_the_full_semantic_weight(): void
    {
        $points = $this->callPrivate('toPoints', ['a' => 1.0, 'b' => 0.65, 'c' => 0.30]);

        $this->assertEqualsWithDelta(10.0, $points['a'], 1e-9);  // ceiling
        $this->assertEqualsWithDelta(5.0, $points['b'], 1e-9);   // halfway up the span
        $this->assertEqualsWithDelta(0.0, $points['c'], 1e-9);   // exactly on the floor
    }

    #[Test]
    public function the_best_of_a_weak_field_does_not_get_promoted_to_a_perfect_score(): void
    {
        // Ceiling is floored at MIN_COSINE + 0.05, so a 0.31 cosine scores 2
        // of a possible 10 — not 10 for merely being the least bad option.
        $points = $this->callPrivate('toPoints', ['a' => 0.31]);

        $this->assertEqualsWithDelta(2.0, $points['a'], 1e-9);
    }

    #[Test]
    public function rescaling_preserves_the_ranking(): void
    {
        $points = $this->callPrivate('toPoints', ['low' => 0.4, 'high' => 0.9, 'mid' => 0.6]);
        arsort($points);

        $this->assertSame(['high', 'mid', 'low'], array_keys($points));
    }

    #[Test]
    public function nothing_above_the_floor_means_no_points(): void
    {
        $this->assertSame([], $this->callPrivate('toPoints', []));
    }

    /*
    | normalize() — scale a count map to 0..1 so signals can be added.
    */

    #[Test]
    public function normalize_puts_the_largest_value_at_one_and_keeps_the_ratios(): void
    {
        $this->assertSame(
            ['a' => 1.0, 'b' => 0.5, 'c' => 0.25],
            $this->callPrivate('normalize', ['a' => 8.0, 'b' => 4.0, 'c' => 2.0])
        );
    }

    #[Test]
    public function normalize_refuses_to_divide_by_zero(): void
    {
        $this->assertSame([], $this->callPrivate('normalize', []));
        $this->assertSame([], $this->callPrivate('normalize', ['a' => 0.0, 'b' => 0.0]));
        $this->assertSame([], $this->callPrivate('normalize', ['a' => -3.0]));
    }

    /*
    | accumulate() — fold a weighted score map into the running total.
    */

    #[Test]
    public function accumulate_adds_weighted_scores_into_the_running_total(): void
    {
        $add = $this->accumulator();

        $totals = ['App\Models\Frame:1' => 2.0];
        $add($totals, ['App\Models\Frame:1' => 4.0, 'App\Models\Frame:2' => 1.0], 0.5);

        $this->assertSame(['App\Models\Frame:1' => 4.0, 'App\Models\Frame:2' => 0.5], $totals);
    }

    #[Test]
    public function a_signal_weighted_to_zero_changes_nothing(): void
    {
        $add = $this->accumulator();

        $totals = ['App\Models\Frame:1' => 2.0];
        $add($totals, ['App\Models\Frame:1' => 99.0], 0.0);

        $this->assertSame(['App\Models\Frame:1' => 2.0], $totals);
    }

    /*
    | key() / cacheKey() — the identity a score map is keyed by.
    */

    #[Test]
    public function a_product_key_is_its_class_and_id(): void
    {
        $lens = new ContactLens;
        $lens->id = 9;

        $this->assertSame('App\Models\Frame:7', $this->callPrivate('key', $this->frame(7)));
        $this->assertSame('App\Models\ContactLens:9', $this->callPrivate('key', $lens));
    }

    #[Test]
    public function the_cache_key_carries_the_version_and_drops_backslashes(): void
    {
        $key = $this->callPrivate('cacheKey', 'similar', $this->frame(7));

        $this->assertSame('rec:v2:similar:App.Models.Frame:7', $key);
        $this->assertStringNotContainsString('\\', $key);
    }

    /*
    | tasteVector() — the weighted centroid of what a shopper likes.
    */

    #[Test]
    public function a_single_seed_gives_back_its_own_vector(): void
    {
        $this->seedMatrix();

        $this->assertEqualsWithDelta(
            [1.0, 0.0, 0.0],
            $this->callPrivate('tasteVector', collect([$this->affinity(1, 1.0)])),
            1e-9
        );
    }

    #[Test]
    public function the_centroid_leans_toward_the_heavier_seed_and_stays_unit_length(): void
    {
        $this->seedMatrix();

        // Frame 1 is [1,0,0] at weight 3; frame 3 is [0,1,0] at weight 1.
        $taste = $this->callPrivate('tasteVector', collect([$this->affinity(1, 3.0), $this->affinity(3, 1.0)]));

        $this->assertGreaterThan($taste[1], $taste[0]);
        $this->assertEqualsWithDelta(1.0, sqrt(array_sum(array_map(fn ($v) => $v * $v, $taste))), 1e-9);
    }

    #[Test]
    public function seeds_with_no_stored_vector_are_skipped_not_counted(): void
    {
        $this->seedMatrix();

        // Frame 999 has never been embedded; the centroid is frame 1 alone.
        $taste = $this->callPrivate('tasteVector', collect([$this->affinity(1, 1.0), $this->affinity(999, 5.0)]));

        $this->assertEqualsWithDelta([1.0, 0.0, 0.0], $taste, 1e-9);
    }

    #[Test]
    public function a_shopper_whose_products_are_all_unembedded_has_no_taste_vector(): void
    {
        $this->seedMatrix();

        $this->assertNull($this->callPrivate('tasteVector', collect([$this->affinity(999, 1.0)])));
        $this->assertNull($this->callPrivate('tasteVector', collect()));
    }

    #[Test]
    public function seeds_that_cancel_out_exactly_produce_no_vector(): void
    {
        // Opposite vectors sum to the origin, which has no direction to point.
        $this->seedMatrix([
            'App\Models\Frame:1' => [1.0, 0.0, 0.0],
            'App\Models\Frame:2' => [-1.0, 0.0, 0.0],
        ]);

        $this->assertNull($this->callPrivate('tasteVector', collect([$this->affinity(1, 1.0), $this->affinity(2, 1.0)])));
    }

    /*
    | tasteScores() — the centroid scored against the whole catalog.
    */

    #[Test]
    public function taste_scores_rank_the_catalog_and_drop_everything_below_the_floor(): void
    {
        $this->seedMatrix();

        $scores = $this->callPrivate('tasteScores', collect([$this->affinity(1, 1.0)]), 1.0);

        // Frame 3 (cosine 0) and the contact lens (cosine 0) fall under MIN_COSINE.
        $this->assertSame(['App\Models\Frame:1', 'App\Models\Frame:2'], array_keys($scores));
        $this->assertEqualsWithDelta(10.0, $scores['App\Models\Frame:1'], 1e-9);
        $this->assertEqualsWithDelta(7.142857, $scores['App\Models\Frame:2'], 1e-6);
    }

    #[Test]
    public function the_signal_weight_scales_every_score(): void
    {
        $this->seedMatrix();

        $full = $this->callPrivate('tasteScores', collect([$this->affinity(1, 1.0)]), 1.0);
        $half = $this->callPrivate('tasteScores', collect([$this->affinity(1, 1.0)]), 0.5);

        $this->assertEqualsWithDelta($full['App\Models\Frame:1'] / 2, $half['App\Models\Frame:1'], 1e-9);
    }
}
