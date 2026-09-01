<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartEyeglass;
use App\Models\ContactLens;
use App\Models\Frame;
use App\Models\Lens;
use App\Models\Order;
use App\Models\OrderContactLens;
use App\Models\OrderEyeglass;
use App\Models\ProductEmbedding;
use App\Models\ProductView;
use App\Models\User;
use App\Services\CatalogEmbedder;
use App\Services\Recommender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RecommenderTest extends TestCase
{
    use RefreshDatabase;

    private function recommender(): Recommender
    {
        return $this->app->make(Recommender::class);
    }

    private function frame(array $attributes = []): Frame
    {
        return Frame::create(array_merge([
            'name' => 'Frame', 'brand' => 'Acme', 'sku' => 'F-'.uniqid(),
            'lens_width' => 50, 'lens_height' => 40, 'bridge_width' => 18, 'temple_length' => 140,
            'material' => 'acetate', 'category' => 'eyeglasses', 'type' => 'full_rim',
            'shape' => 'round', 'gender' => 'unisex', 'size' => 'medium', 'color' => 'black',
            'price' => 100, 'stock' => 10, 'is_active' => true,
        ], $attributes));
    }

    private function lens(array $attributes = []): ContactLens
    {
        return ContactLens::create(array_merge([
            'name' => 'Lens', 'brand' => 'Acme', 'sku' => 'C-'.uniqid(),
            'type' => 'daily', 'material' => 'hydrogel', 'base_curve' => 8.6, 'diameter' => 14.2,
            'pack_size' => 30, 'price' => 30, 'stock' => 10, 'is_active' => true,
        ], $attributes));
    }

    /**
     * A paid order for the given products. Events are suppressed so the
     * notification pipeline doesn't run for every fixture.
     */
    private function order(User $user, array $products): Order
    {
        return Order::withoutEvents(function () use ($user, $products) {
            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => 'ORD-'.uniqid(),
                'status' => 'delivered',
                'subtotal' => 0, 'total' => 0,
            ]);

            foreach ($products as $product) {
                if ($product instanceof Frame) {
                    OrderEyeglass::create([
                        'order_id' => $order->id,
                        'frame_id' => $product->id,
                        'frame_name' => $product->name,
                        'lens_name' => 'Standard',
                        'frame_unit_price' => $product->price,
                        'lens_unit_price' => 0,
                        'line_total' => $product->price,
                    ]);
                } else {
                    OrderContactLens::create([
                        'order_id' => $order->id,
                        'contact_lens_id' => $product->id,
                        'product_name' => $product->name,
                        'unit_price' => $product->price,
                        'line_total' => $product->price,
                    ]);
                }
            }

            return $order;
        });
    }

    private function logView(Frame|ContactLens $product, ?User $user = null, ?string $session = null): void
    {
        ProductView::create([
            'viewable_type' => $product->getMorphClass(),
            'viewable_id' => $product->id,
            'user_id' => $user?->id,
            'session_id' => $user ? null : $session,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Content similarity
    |--------------------------------------------------------------------------
    */

    public function test_it_ranks_frames_by_shared_attributes(): void
    {
        $seed = $this->frame(['shape' => 'round', 'material' => 'acetate', 'price' => 100]);
        $close = $this->frame(['shape' => 'round', 'material' => 'acetate', 'price' => 110]);
        $far = $this->frame([
            'shape' => 'aviator', 'material' => 'titanium', 'category' => 'sunglasses',
            'type' => 'rimless', 'gender' => 'men', 'size' => 'wide', 'color' => 'gold',
            'brand' => 'Other', 'price' => 900,
        ]);

        $similar = $this->recommender()->similarTo($seed);

        $this->assertTrue($similar->contains(fn ($f) => $f->is($close)));
        $this->assertFalse($similar->contains(fn ($f) => $f->is($far)));
    }

    public function test_it_ranks_contact_lenses_by_schedule_and_fit(): void
    {
        $seed = $this->lens(['type' => 'daily', 'base_curve' => 8.6, 'diameter' => 14.2]);
        $close = $this->lens(['type' => 'daily', 'base_curve' => 8.7, 'diameter' => 14.2]);
        $far = $this->lens([
            'type' => 'yearly', 'material' => 'silicone_hydrogel', 'brand' => 'Other',
            'base_curve' => 9.4, 'diameter' => 13.0, 'pack_size' => 1, 'price' => 400,
        ]);

        $similar = $this->recommender()->similarTo($seed);

        $this->assertTrue($similar->contains(fn ($l) => $l->is($close)));
        $this->assertFalse($similar->contains(fn ($l) => $l->is($far)));
    }

    public function test_it_never_recommends_unavailable_products(): void
    {
        $seed = $this->frame();
        $soldOut = $this->frame(['stock' => 0]);
        $retired = $this->frame(['is_active' => false]);
        $available = $this->frame();

        $similar = $this->recommender()->similarTo($seed);

        $this->assertTrue($similar->contains(fn ($f) => $f->is($available)));
        $this->assertFalse($similar->contains(fn ($f) => $f->is($soldOut)));
        $this->assertFalse($similar->contains(fn ($f) => $f->is($retired)));
    }

    public function test_it_returns_nothing_when_the_catalog_has_no_close_match(): void
    {
        $seed = $this->frame(['shape' => 'round', 'price' => 100]);
        $this->frame([
            'shape' => 'aviator', 'material' => 'titanium', 'category' => 'sports',
            'type' => 'rimless', 'gender' => 'kids', 'size' => 'narrow', 'color' => 'red',
            'brand' => 'Other', 'price' => 800,
        ]);

        $this->assertTrue($this->recommender()->similarTo($seed)->isEmpty());
    }

    /*
    |--------------------------------------------------------------------------
    | Co-purchase
    |--------------------------------------------------------------------------
    */

    public function test_it_recommends_across_catalogs_from_purchase_history(): void
    {
        $frame = $this->frame();
        $lens = $this->lens();

        // Two customers bought the frame, and both came back for the lenses.
        foreach (range(1, 2) as $ignored) {
            $customer = User::factory()->create();
            $this->order($customer, [$frame]);
            $this->order($customer, [$lens]);
        }

        $alsoBought = $this->recommender()->alsoBought($frame);

        $this->assertTrue($alsoBought->contains(fn ($p) => $p->is($lens)));
    }

    public function test_also_bought_is_empty_without_purchase_history(): void
    {
        $frame = $this->frame();
        $this->frame();

        // There are similar frames in the catalog, but the heading claims
        // other customers bought them — so it must stay empty.
        $this->assertTrue($this->recommender()->alsoBought($frame)->isEmpty());
    }

    public function test_cancelled_orders_do_not_create_recommendations(): void
    {
        $frame = $this->frame();
        $lens = $this->lens();

        $customer = User::factory()->create();
        $this->order($customer, [$frame]);

        $cancelled = $this->order($customer, [$lens]);
        Order::withoutEvents(fn () => $cancelled->update(['status' => 'cancelled']));

        $this->assertTrue($this->recommender()->alsoBought($frame)->isEmpty());
    }

    public function test_popularity_damping_favours_the_specific_signal(): void
    {
        $seed = $this->frame();
        $niche = $this->lens(['name' => 'Niche']);
        $bestSeller = $this->lens(['name' => 'Best seller']);

        // Two of the seed's three buyers also took the niche lens; all three
        // took the best-seller — but so did fifteen unrelated customers, so
        // its co-occurrence is explained by its own popularity, not by the
        // seed.
        foreach (range(1, 3) as $ignored) {
            $buyer = User::factory()->create();
            $this->order($buyer, [$seed, $bestSeller]);
        }

        foreach (range(1, 2) as $ignored) {
            $buyer = User::factory()->create();
            $this->order($buyer, [$seed, $niche]);
        }

        foreach (range(1, 15) as $ignored) {
            $this->order(User::factory()->create(), [$bestSeller]);
        }

        $ranked = $this->recommender()->alsoBought($seed);

        $this->assertTrue($ranked->first()->is($niche));
    }

    /*
    |--------------------------------------------------------------------------
    | Co-view
    |--------------------------------------------------------------------------
    */

    public function test_co_viewing_surfaces_a_frame_attributes_alone_would_miss(): void
    {
        $seed = $this->frame(['shape' => 'round', 'price' => 100]);
        $odd = $this->frame([
            'shape' => 'aviator', 'material' => 'titanium', 'category' => 'sunglasses',
            'type' => 'rimless', 'gender' => 'men', 'size' => 'wide', 'color' => 'gold',
            'brand' => 'Other', 'price' => 900,
        ]);

        // Attributes alone put this pair nowhere near each other.
        $this->assertFalse($this->recommender()->similarTo($seed)->contains(fn ($f) => $f->is($odd)));

        foreach (['s-1', 's-2', 's-3'] as $session) {
            $this->logView($seed, session: $session);
            $this->logView($odd, session: $session);
        }

        $this->app->make('cache')->flush();

        $this->assertTrue($this->recommender()->similarTo($seed)->contains(fn ($f) => $f->is($odd)));
    }

    /*
    |--------------------------------------------------------------------------
    | Personalization
    |--------------------------------------------------------------------------
    */

    public function test_it_personalizes_a_guest_from_their_session(): void
    {
        $viewed = $this->frame(['shape' => 'cat_eye', 'brand' => 'Lumen']);
        $match = $this->frame(['shape' => 'cat_eye', 'brand' => 'Lumen']);

        $this->logView($viewed, session: 'guest-session');

        $recommended = $this->recommender()->forShopper(null, 'guest-session');

        $this->assertTrue($recommended->contains(fn ($f) => $f->is($match)));
        $this->assertFalse($recommended->contains(fn ($f) => $f->is($viewed)));
    }

    public function test_it_never_recommends_something_already_bought(): void
    {
        $bought = $this->frame(['shape' => 'cat_eye']);
        $other = $this->frame(['shape' => 'cat_eye']);

        $customer = User::factory()->create();
        $this->order($customer, [$bought]);

        $recommended = $this->recommender()->forShopper($customer, null);

        $this->assertTrue($recommended->contains(fn ($f) => $f->is($other)));
        $this->assertFalse($recommended->contains(fn ($f) => $f->is($bought)));
    }

    /*
    |--------------------------------------------------------------------------
    | Semantic similarity
    |--------------------------------------------------------------------------
    |
    | These build vectors by hand rather than running the transformer. The
    | model is a build-time dependency and a fixed function of its input —
    | running it here would make the suite slow and prove nothing about this
    | code. What needs testing is the ranking built on top of the vectors,
    | which is exactly what hand-placed points in a known space pin down.
    |
    */

    /**
     * Store a unit-length embedding for a product, normalizing whatever
     * coordinates the test asked for.
     *
     * @param  array<int, float>  $coordinates
     */
    private function embed(Frame|ContactLens $product, array $coordinates, string $model = CatalogEmbedder::MODEL): void
    {
        $length = sqrt(array_sum(array_map(fn ($v) => $v * $v, $coordinates)));
        $unit = array_map(fn ($v) => $v / $length, $coordinates);

        ProductEmbedding::updateOrCreate(
            [
                'embeddable_type' => $product->getMorphClass(),
                'embeddable_id' => $product->getKey(),
            ],
            [
                'model' => $model,
                'dimensions' => count($unit),
                'vector' => ProductEmbedding::encode($unit),
                'content_hash' => hash('sha256', $product->name),
            ],
        );

        Cache::forget(CatalogEmbedder::CACHE_KEY);
    }

    public function test_a_vector_survives_the_round_trip_through_storage(): void
    {
        $original = [0.5, -0.25, 0.125, 0.0625];

        $decoded = ProductEmbedding::decode(ProductEmbedding::encode($original));

        $this->assertCount(4, $decoded);

        foreach ($original as $i => $value) {
            $this->assertEqualsWithDelta($value, $decoded[$i], 1e-6);
        }
    }

    public function test_it_ranks_by_distance_in_embedding_space(): void
    {
        // Deliberately identical on every attribute, so the old scoring
        // could not separate them and only the vectors can.
        $seed = $this->frame(['name' => 'Seed']);
        $near = $this->frame(['name' => 'Near']);
        $mid = $this->frame(['name' => 'Mid']);

        $this->embed($seed, [1.0, 0.0]);
        $this->embed($near, [0.95, 0.31]);   // cosine ~0.95
        $this->embed($mid, [0.55, 0.84]);    // cosine ~0.55

        $ranked = $this->recommender()->similarTo($seed);

        $this->assertSame(['Near', 'Mid'], $ranked->pluck('name')->all());
    }

    public function test_it_drops_products_below_the_cosine_floor(): void
    {
        $seed = $this->frame(['name' => 'Seed']);
        $related = $this->frame(['name' => 'Related']);
        $unrelated = $this->frame(['name' => 'Unrelated']);

        $this->embed($seed, [1.0, 0.0]);
        $this->embed($related, [0.9, 0.44]);
        // cosine ~0.10, well under the 0.30 floor.
        $this->embed($unrelated, [0.1, 0.995]);

        $ranked = $this->recommender()->similarTo($seed);

        $this->assertTrue($ranked->contains(fn ($f) => $f->is($related)));
        $this->assertFalse($ranked->contains(fn ($f) => $f->is($unrelated)));
    }

    public function test_similar_stays_inside_one_catalog(): void
    {
        $seed = $this->frame(['name' => 'Seed']);
        $lens = $this->lens(['name' => 'Very Close Lens']);
        $frame = $this->frame(['name' => 'Slightly Further Frame']);

        // The lens sits nearer the seed than the frame does, and is still
        // not an alternative to a pair of glasses.
        $this->embed($seed, [1.0, 0.0]);
        $this->embed($lens, [0.99, 0.14]);
        $this->embed($frame, [0.8, 0.6]);

        $ranked = $this->recommender()->similarTo($seed);

        $this->assertTrue($ranked->contains(fn ($p) => $p->is($frame)));
        $this->assertFalse($ranked->contains(fn ($p) => $p->is($lens)));
    }

    public function test_the_personalized_rail_does_cross_catalogs(): void
    {
        $viewed = $this->frame(['name' => 'Viewed']);
        $lens = $this->lens(['name' => 'Matching Lens']);

        $this->embed($viewed, [1.0, 0.0]);
        $this->embed($lens, [0.9, 0.44]);

        $this->logView($viewed, session: 'guest');

        $recommended = $this->recommender()->forShopper(null, 'guest');

        $this->assertTrue($recommended->contains(fn ($p) => $p->is($lens)));
    }

    public function test_the_taste_vector_sits_between_the_seeds(): void
    {
        $left = $this->frame(['name' => 'Left']);
        $right = $this->frame(['name' => 'Right']);
        $between = $this->frame(['name' => 'Between']);
        $beyond = $this->frame(['name' => 'Beyond']);

        // Two seeds at right angles; the centroid points between them.
        $this->embed($left, [1.0, 0.0]);
        $this->embed($right, [0.0, 1.0]);
        // Sits on the bisector — nearest the centroid, though it is not the
        // nearest neighbour of either seed on its own.
        $this->embed($between, [0.707, 0.707]);
        // Closer to one seed than $between is, but away from the centroid.
        $this->embed($beyond, [0.98, -0.2]);

        $this->logView($left, session: 'guest');
        $this->logView($right, session: 'guest');

        $recommended = $this->recommender()->forShopper(null, 'guest');

        $this->assertTrue($recommended->first()->is($between));
    }

    public function test_it_ignores_vectors_from_a_different_model(): void
    {
        $seed = $this->frame(['name' => 'Seed']);
        $near = $this->frame(['name' => 'Near']);
        $stale = $this->frame(['name' => 'Stale']);

        $this->embed($seed, [1.0, 0.0]);
        $this->embed($near, [0.9, 0.44]);
        // Identical coordinates to the seed, so this would rank first if the
        // model filter were missing — but coordinates from one model mean
        // nothing in another's space, and must never be compared across.
        $this->embed($stale, [1.0, 0.0], model: 'some/older-model');

        $ranked = $this->recommender()->similarTo($seed);

        $this->assertTrue($ranked->contains(fn ($f) => $f->is($near)));
        $this->assertFalse(
            $ranked->contains(fn ($f) => $f->is($stale)),
            'A vector from another model was treated as comparable.',
        );
    }

    public function test_an_empty_semantic_result_is_not_padded_from_attributes(): void
    {
        // Identical on every attribute — the fallback would happily pair
        // them — but the model places them far apart, and that answer stands.
        $seed = $this->frame(['name' => 'Seed']);
        $other = $this->frame(['name' => 'Other']);

        $this->embed($seed, [1.0, 0.0]);
        $this->embed($other, [0.05, 0.99]);

        $this->assertTrue($this->recommender()->similarTo($seed)->isEmpty());
    }

    public function test_it_falls_back_to_attributes_when_a_product_has_no_vector(): void
    {
        // Nothing embedded at all — the catalog:embed step has not run.
        $seed = $this->frame(['shape' => 'cat_eye', 'brand' => 'Lumen']);
        $match = $this->frame(['shape' => 'cat_eye', 'brand' => 'Lumen']);

        $ranked = $this->recommender()->similarTo($seed);

        $this->assertTrue($ranked->contains(fn ($f) => $f->is($match)));
    }

    /*
    |--------------------------------------------------------------------------
    | Keeping embeddings honest
    |--------------------------------------------------------------------------
    */

    public function test_selling_a_unit_does_not_invalidate_an_embedding(): void
    {
        $frame = $this->frame(['stock' => 10]);
        $embedder = $this->app->make(CatalogEmbedder::class);

        ProductEmbedding::create([
            'embeddable_type' => $frame->getMorphClass(),
            'embeddable_id' => $frame->getKey(),
            'model' => CatalogEmbedder::MODEL,
            'dimensions' => 2,
            'vector' => ProductEmbedding::encode([1.0, 0.0]),
            'content_hash' => hash('sha256', $embedder->describe($frame)),
        ]);

        $frame->update(['stock' => 9]);

        // Stock is not part of the described text, so re-embedding the whole
        // catalog after every checkout would be pure waste.
        $this->assertDatabaseCount('product_embeddings', 1);
    }

    public function test_renaming_a_product_invalidates_its_embedding(): void
    {
        $frame = $this->frame(['name' => 'Harbor Classic']);
        $embedder = $this->app->make(CatalogEmbedder::class);

        ProductEmbedding::create([
            'embeddable_type' => $frame->getMorphClass(),
            'embeddable_id' => $frame->getKey(),
            'model' => CatalogEmbedder::MODEL,
            'dimensions' => 2,
            'vector' => ProductEmbedding::encode([1.0, 0.0]),
            'content_hash' => hash('sha256', $embedder->describe($frame)),
        ]);

        $frame->update(['name' => 'Harbor Reader', 'shape' => 'aviator']);

        $this->assertDatabaseCount('product_embeddings', 0);
    }

    public function test_the_described_document_reads_as_prose(): void
    {
        $frame = $this->frame([
            'name' => 'Harbor Classic', 'brand' => 'Optix', 'shape' => 'cat_eye',
            'type' => 'semi_rimless', 'material' => 'acetate', 'color' => 'Tortoise',
            'price' => 89,
        ]);

        $document = $this->app->make(CatalogEmbedder::class)->describe($frame);

        // The enums have to reach the model as English, not as column values:
        // "cat_eye" is one unknown token, "cat-eye" is a described shape.
        $this->assertStringContainsString('cat-eye', $document);
        $this->assertStringContainsString('semi-rimless', $document);
        $this->assertStringNotContainsString('cat_eye', $document);
        $this->assertStringNotContainsString('semi_rimless', $document);

        $this->assertStringContainsString('Harbor Classic', $document);
        $this->assertStringContainsString('Optix', $document);
        $this->assertStringContainsString('tortoise', $document);
        $this->assertStringContainsString('$89.00', $document);
    }

    public function test_a_lens_document_states_the_per_lens_price(): void
    {
        $lens = $this->lens(['name' => 'DailyClear', 'pack_size' => 30, 'price' => 45]);

        $document = $this->app->make(CatalogEmbedder::class)->describe($lens);

        // $1.50 per lens is the number a wearer compares on, and it is in no
        // column — a 30-pack at $45 looks dearer than a 6-pack at $18.
        $this->assertStringContainsString('$1.50 per lens', $document);
        $this->assertStringContainsString('daily disposable', $document);
    }

    public function test_it_recommends_nothing_to_a_shopper_with_no_history(): void
    {
        $this->frame();

        $this->assertTrue($this->recommender()->forShopper(null, 'brand-new-session')->isEmpty());
    }

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    */

    public function test_the_frame_page_shows_recommendations(): void
    {
        $seed = $this->frame(['name' => 'Harbor Classic']);
        $match = $this->frame(['name' => 'Harbor Reader']);

        $this->get(route('frames.show', $seed))
            ->assertOk()
            ->assertSee('You may also like')
            ->assertSee($match->name);
    }

    public function test_the_contact_lens_page_shows_recommendations(): void
    {
        $seed = $this->lens(['name' => 'Clarity One']);
        $match = $this->lens(['name' => 'Clarity Plus']);

        $this->get(route('contact-lenses.show', $seed))
            ->assertOk()
            ->assertSee('You may also like')
            ->assertSee($match->name);
    }

    public function test_the_product_page_rails_do_not_repeat_a_product(): void
    {
        $seed = $this->frame();
        $both = $this->frame();

        $customer = User::factory()->create();
        $this->order($customer, [$seed, $both]);

        ['similar' => $similar, 'alsoBought' => $alsoBought] = $this->recommender()->forProductPage($seed);

        $this->assertTrue($alsoBought->contains(fn ($f) => $f->is($both)));
        $this->assertFalse($similar->contains(fn ($f) => $f->is($both)));
    }

    public function test_the_order_page_explains_why_it_recommends(): void
    {
        $bought = $this->frame(['name' => 'Harbor Classic', 'shape' => 'cat_eye']);
        $match = $this->frame(['name' => 'Harbor Reader', 'shape' => 'cat_eye']);

        $customer = User::factory()->create();
        $order = $this->order($customer, [$bought]);

        $this->actingAs($customer)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('You bought Harbor Classic')
            ->assertSee($match->name);
    }

    public function test_the_home_page_shows_a_personalized_rail_once_there_is_history(): void
    {
        $viewed = $this->frame(['name' => 'Harbor Classic', 'shape' => 'cat_eye']);
        $match = $this->frame(['name' => 'Harbor Reader', 'shape' => 'cat_eye']);

        $customer = User::factory()->create();
        $this->logView($viewed, user: $customer);

        $this->actingAs($customer)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Picked from what you have been looking at')
            ->assertSee($match->name);
    }

    public function test_the_cart_cross_sells_without_repeating_the_cart(): void
    {
        $inCart = $this->frame(['name' => 'Harbor Classic', 'shape' => 'cat_eye']);
        $match = $this->frame(['name' => 'Harbor Reader', 'shape' => 'cat_eye']);

        $customer = User::factory()->create();
        $lens = Lens::create([
            'name' => 'Standard Single Vision', 'material' => 'plastic',
            'type' => 'single_vision', 'price' => 25, 'is_active' => true,
        ]);

        $cart = Cart::create(['user_id' => $customer->id]);
        CartEyeglass::create([
            'cart_id' => $cart->id,
            'frame_id' => $inCart->id,
            'lens_id' => $lens->id,
            'quantity' => 1,
        ]);

        $this->actingAs($customer)
            ->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Goes well with your cart')
            ->assertSee($match->name);
    }

    public function test_the_home_page_hides_the_rail_for_a_first_time_visitor(): void
    {
        $this->frame();

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Picked from what you have been looking at');
    }
}
