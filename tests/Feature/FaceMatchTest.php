<?php

namespace Tests\Feature;

use App\Models\Frame;
use App\Services\FaceShapeClassifier;
use Database\Seeders\FaceShapeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaceMatchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A plausible set of scan measurements, overridable per-case. Defaults to
     * an oval face — the fallback classification.
     */
    private function measurements(array $overrides = []): array
    {
        return array_merge([
            'face_length_mm' => 185.0,
            'cheekbone_width_mm' => 138.0,
            'jaw_width_mm' => 112.0,
            'forehead_width_mm' => 122.0,
            'pd_mm' => 63.0,
            'pd_left_mm' => 31.5,
            'pd_right_mm' => 31.5,
            'yaw_ratio' => 1.0,
        ], $overrides);
    }

    private function frame(array $attributes = []): Frame
    {
        return Frame::create(array_merge([
            'name' => 'T', 'brand' => 'B', 'sku' => 'SKU-'.uniqid(),
            'lens_width' => 50, 'lens_height' => 40, 'bridge_width' => 18, 'temple_length' => 140,
            'material' => 'acetate', 'category' => 'eyeglasses', 'type' => 'full_rim',
            'gender' => 'unisex', 'price' => 10, 'stock' => 1, 'is_active' => true,
        ], $attributes));
    }

    public function test_it_classifies_the_six_face_shapes(): void
    {
        $classifier = new FaceShapeClassifier;

        $cases = [
            // length/cheek > 1.50
            'oblong' => ['face_length_mm' => 220.0, 'cheekbone_width_mm' => 138.0],
            // short face, jaw nearly as wide as the cheekbones
            'square' => ['face_length_mm' => 165.0, 'jaw_width_mm' => 132.0],
            // short face, softer jaw
            'round' => ['face_length_mm' => 165.0, 'jaw_width_mm' => 122.0],
            // wide forehead, narrow chin
            'heart' => ['forehead_width_mm' => 135.0, 'jaw_width_mm' => 104.0],
            // cheekbones widest, both ends narrow
            'diamond' => ['forehead_width_mm' => 112.0, 'jaw_width_mm' => 110.0],
            // balanced — the default
            'oval' => [],
        ];

        foreach ($cases as $expected => $overrides) {
            $this->assertSame(
                $expected,
                $classifier->classify($this->measurements($overrides)),
                "Expected {$expected} for ".json_encode($overrides)
            );
        }
    }

    public function test_scan_returns_the_matching_recommendation_url(): void
    {
        $this->seed(FaceShapeSeeder::class);

        $response = $this->postJson(route('face-match.analyze'), $this->measurements());

        $response->assertOk()
            ->assertJsonStructure(['redirect']);

        $this->assertStringEndsWith('/face-match/oval', $response->json('redirect'));
        $this->assertSame('oval', session('face_scan.shape'));
        $this->assertEqualsWithDelta(63.0, session('face_scan.pd_mm'), 0.01);
    }

    public function test_it_rejects_measurements_outside_human_range(): void
    {
        $this->seed(FaceShapeSeeder::class);

        // Client-supplied values are untrusted — a tampered PD would otherwise
        // poison the frame-sizing order.
        $this->postJson(route('face-match.analyze'), $this->measurements(['pd_mm' => 400.0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('pd_mm');

        // A turned head foreshortens one side and corrupts every width.
        $this->postJson(route('face-match.analyze'), $this->measurements(['yaw_ratio' => 1.6]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('yaw_ratio');
    }

    public function test_recommendations_are_ordered_by_optical_fit(): void
    {
        $this->seed(FaceShapeSeeder::class);

        $oval = \App\Models\FaceShape::where('slug', 'oval')->first();

        // Frame PD is lens_width + bridge_width, against a 63mm wearer PD.
        // frame_width is pinned to the face width on all three so the width
        // term cancels out and the ordering isolates the PD signal.
        $narrow = $this->frame(['name' => 'NarrowPd', 'lens_width' => 44, 'bridge_width' => 14, 'frame_width' => 138]); // 58mm, off by 5
        $wide = $this->frame(['name' => 'WidePd', 'lens_width' => 52, 'bridge_width' => 19, 'frame_width' => 138]);     // 71mm, off by 8
        $exact = $this->frame(['name' => 'ExactPd', 'lens_width' => 48, 'bridge_width' => 15, 'frame_width' => 138]);   // 63mm, off by 0

        $oval->frames()->attach([$narrow->id, $wide->id, $exact->id]);

        $this->withSession(['face_scan' => $this->measurements(['shape' => 'oval'])])
            ->get(route('face-match.recommend', $oval))
            ->assertOk()
            ->assertSeeInOrder(['ExactPd', 'NarrowPd', 'WidePd']);
    }

    public function test_manual_shape_pick_works_without_a_scan(): void
    {
        $this->seed(FaceShapeSeeder::class);

        $round = \App\Models\FaceShape::where('slug', 'round')->first();
        $round->frames()->attach($this->frame(['name' => 'Angular'])->id);

        $this->get(route('face-match.recommend', $round))
            ->assertOk()
            ->assertSee('Angular')
            ->assertDontSee('Your measurements');
    }
}
