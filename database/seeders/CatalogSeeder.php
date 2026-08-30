<?php

namespace Database\Seeders;

use App\Models\ContactLens;
use App\Models\FaceShape;
use App\Models\Frame;
use App\Models\Lens;
use App\Models\LensFeature;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class CatalogSeeder extends Seeder
{
    /**
     * A small, realistic starter catalog so the storefront, cart, and admin
     * screens have something to work with in development. Not meant to be
     * exhaustive — replace/expand through the admin inventory screens.
     */
    public function run(): void
    {
        $this->seedLenses();
        $this->seedFrames();
        $this->seedContactLenses();
    }

    private function seedLenses(): void
    {
        $allFeatures = LensFeature::pluck('id', 'slug');

        $packages = [
            [
                'name' => 'Standard Single Vision',
                'material' => 'plastic',
                'type' => 'single_vision',
                'refractive_index' => 1.50,
                'price' => 25.00,
                'description' => 'Everyday plastic single vision lens.',
                'features' => ['anti-blue-light', 'uv-protection', 'anti-reflective-coating', 'scratch-resistant-coating'],
            ],
            [
                'name' => 'Polycarbonate Single Vision',
                'material' => 'polycarbonate',
                'type' => 'single_vision',
                'refractive_index' => 1.59,
                'price' => 45.00,
                'description' => 'Impact-resistant, lighter than standard plastic. Good for kids and sports frames.',
                'features' => ['anti-blue-light', 'uv-protection', 'anti-reflective-coating', 'scratch-resistant-coating', 'photochromic'],
            ],
            [
                'name' => 'High-Index Progressive',
                'material' => 'high_index',
                'type' => 'progressive',
                'refractive_index' => 1.67,
                'price' => 120.00,
                'description' => 'Thin, lightweight progressive lens for strong prescriptions.',
                'features' => ['anti-blue-light', 'uv-protection', 'anti-reflective-coating', 'scratch-resistant-coating', 'photochromic'],
            ],
            [
                'name' => 'Reading',
                'material' => 'plastic',
                'type' => 'reading',
                'refractive_index' => 1.50,
                'price' => 20.00,
                'description' => 'Simple magnification lens for reading glasses.',
                'features' => ['anti-blue-light', 'anti-reflective-coating', 'scratch-resistant-coating'],
            ],
            [
                'name' => 'Non-Prescription Sun Lens',
                'material' => 'plastic',
                'type' => 'plano',
                'refractive_index' => 1.50,
                'price' => 15.00,
                'description' => 'Clear or tinted lens with no prescription, for sunglasses-only purchases.',
                'features' => ['uv-protection', 'polarized', 'photochromic', 'scratch-resistant-coating'],
            ],
        ];

        foreach ($packages as $package) {
            $features = $package['features'];
            unset($package['features']);

            $lens = Lens::updateOrCreate(['name' => $package['name']], $package + ['is_active' => true]);

            $ids = collect($features)->map(fn ($slug) => $allFeatures[$slug] ?? null)->filter()->values();
            $lens->features()->sync($ids);
        }
    }

    private function seedFrames(): void
    {
        $faceShapes = FaceShape::pluck('id', 'slug');

        // shape => face shapes it's typically recommended for (styling rule
        // of thumb; the shop owner can adjust per-frame from the admin panel).
        $recommendationsByShape = [
            'round' => ['square', 'diamond'],
            'square' => ['round', 'oval'],
            'rectangle' => ['round', 'oval'],
            'oval' => ['square', 'diamond', 'heart'],
            'cat_eye' => ['round', 'square', 'heart'],
            'aviator' => ['heart', 'square', 'diamond', 'oval'],
            'wayfarer' => ['round', 'oval'],
            'browline' => ['oval', 'round', 'diamond'],
            'geometric' => ['round', 'oval'],
            'hexagonal' => ['round', 'square'],
        ];

        $frames = [
            ['name' => 'Harbor Classic', 'brand' => 'Optix', 'sku' => 'FR-1001', 'lens_width' => 52, 'lens_height' => 40, 'bridge_width' => 18, 'temple_length' => 145, 'frame_width' => 138, 'weight_grams' => 22, 'size' => 'medium', 'material' => 'acetate', 'category' => 'eyeglasses', 'type' => 'full_rim', 'shape' => 'round', 'gender' => 'unisex', 'color' => 'Tortoise', 'color_hex' => '#7B5B3A', 'price' => 89.00, 'stock' => 40],
            ['name' => 'Skyline Aviator', 'brand' => 'Optix', 'sku' => 'FR-1002', 'lens_width' => 58, 'lens_height' => 50, 'bridge_width' => 14, 'temple_length' => 140, 'frame_width' => 140, 'weight_grams' => 28, 'size' => 'wide', 'material' => 'metal', 'category' => 'sunglasses', 'type' => 'full_rim', 'shape' => 'aviator', 'gender' => 'men', 'color' => 'Gold', 'color_hex' => '#C9A227', 'price' => 110.00, 'stock' => 25],
            ['name' => 'Willow Cat-Eye', 'brand' => 'Lumina', 'sku' => 'FR-1003', 'lens_width' => 50, 'lens_height' => 42, 'bridge_width' => 17, 'temple_length' => 140, 'frame_width' => 133, 'weight_grams' => 20, 'size' => 'narrow', 'material' => 'acetate', 'category' => 'eyeglasses', 'type' => 'full_rim', 'shape' => 'cat_eye', 'gender' => 'women', 'color' => 'Blush Pink', 'color_hex' => '#E8B4BC', 'price' => 95.00, 'stock' => 30],
            ['name' => 'Denton Square', 'brand' => 'Lumina', 'sku' => 'FR-1004', 'lens_width' => 54, 'lens_height' => 44, 'bridge_width' => 19, 'temple_length' => 145, 'frame_width' => 141, 'weight_grams' => 24, 'size' => 'medium', 'material' => 'metal', 'category' => 'eyeglasses', 'type' => 'semi_rimless', 'shape' => 'square', 'gender' => 'men', 'color' => 'Gunmetal', 'color_hex' => '#3A3B3C', 'price' => 105.00, 'stock' => 35],
            ['name' => 'Pebble Oval', 'brand' => 'Optix', 'sku' => 'FR-1005', 'lens_width' => 49, 'lens_height' => 43, 'bridge_width' => 20, 'temple_length' => 140, 'frame_width' => 132, 'weight_grams' => 18, 'size' => 'narrow', 'material' => 'titanium', 'category' => 'eyeglasses', 'type' => 'rimless', 'shape' => 'oval', 'gender' => 'women', 'color' => 'Silver', 'color_hex' => '#C0C0C0', 'price' => 130.00, 'stock' => 20],
            ['name' => 'Trailblazer Sport', 'brand' => 'FlexFrame', 'sku' => 'FR-1006', 'lens_width' => 56, 'lens_height' => 46, 'bridge_width' => 16, 'temple_length' => 130, 'frame_width' => 136, 'weight_grams' => 26, 'size' => 'medium', 'material' => 'plastic', 'category' => 'sports', 'type' => 'full_rim', 'shape' => 'wayfarer', 'gender' => 'unisex', 'color' => 'Matte Black', 'color_hex' => '#1A1A1A', 'price' => 75.00, 'stock' => 50],
            ['name' => 'Junior Explorer', 'brand' => 'FlexFrame', 'sku' => 'FR-1007', 'lens_width' => 46, 'lens_height' => 38, 'bridge_width' => 16, 'temple_length' => 125, 'frame_width' => 122, 'weight_grams' => 15, 'size' => 'narrow', 'material' => 'plastic', 'category' => 'eyeglasses', 'type' => 'full_rim', 'shape' => 'round', 'gender' => 'kids', 'color' => 'Sky Blue', 'color_hex' => '#7EC8E3', 'price' => 55.00, 'stock' => 45],
            ['name' => 'Ridgeline Browline', 'brand' => 'Lumina', 'sku' => 'FR-1008', 'lens_width' => 53, 'lens_height' => 41, 'bridge_width' => 18, 'temple_length' => 145, 'frame_width' => 139, 'weight_grams' => 23, 'size' => 'medium', 'material' => 'mixed', 'category' => 'eyeglasses', 'type' => 'semi_rimless', 'shape' => 'browline', 'gender' => 'men', 'color' => 'Havana', 'color_hex' => '#8B5A2B', 'price' => 99.00, 'stock' => 28],
        ];

        foreach ($frames as $frame) {
            $shape = $frame['shape'];
            $frame['description'] ??= "{$frame['name']} — {$frame['color']} {$frame['material']} frame.";

            $record = Frame::updateOrCreate(['sku' => $frame['sku']], $frame + ['is_active' => true]);

            $recommended = collect($recommendationsByShape[$shape] ?? [])
                ->map(fn ($slug) => $faceShapes[$slug] ?? null)
                ->filter()
                ->values();
            $record->faceShapes()->sync($recommended);

            // Only ever *add* a starter image, never overwrite one.
            //
            // This used to be an updateOrCreate, which meant re-running the
            // seeder on an environment where staff had uploaded real photos
            // silently replaced their paths with the placeholder name below —
            // a file that doesn't ship with the repo — leaving every card
            // pointing at a 404.
            //
            // The existence check matters too: a row whose file is missing
            // renders a broken <img>, whereas no row at all renders the
            // designed placeholder in the frame-card component.
            $placeholder = "frames/{$frame['sku']}-front.jpg";

            if (! $record->images()->where('sort_order', 0)->exists()
                && Storage::disk('public')->exists($placeholder)) {
                $record->images()->create([
                    'sort_order' => 0,
                    'path' => $placeholder,
                    'alt_text' => "{$frame['name']} front view",
                    'is_primary' => true,
                ]);
            }
        }
    }

    private function seedContactLenses(): void
    {
        $lenses = [
            ['name' => 'DailyClear', 'brand' => 'VisionPlus', 'sku' => 'CL-2001', 'type' => 'daily', 'material' => 'silicone_hydrogel', 'diameter' => 14.20, 'base_curve' => 8.60, 'pack_size' => 30, 'expiry_months' => null, 'price' => 32.00, 'stock' => 200],
            ['name' => 'MonthlyComfort', 'brand' => 'VisionPlus', 'sku' => 'CL-2002', 'type' => 'monthly', 'material' => 'silicone_hydrogel', 'diameter' => 14.00, 'base_curve' => 8.50, 'pack_size' => 6, 'expiry_months' => 1, 'price' => 28.00, 'stock' => 150],
            ['name' => 'BiWeekly Hydro', 'brand' => 'ClearView', 'sku' => 'CL-2003', 'type' => 'biweekly', 'material' => 'hydrogel', 'diameter' => 14.20, 'base_curve' => 8.70, 'pack_size' => 12, 'expiry_months' => null, 'price' => 24.00, 'stock' => 120],
            ['name' => 'ColorPop Grey', 'brand' => 'ClearView', 'sku' => 'CL-2004', 'type' => 'monthly', 'material' => 'hydrogel', 'color' => 'Grey', 'diameter' => 14.20, 'base_curve' => 8.60, 'pack_size' => 2, 'expiry_months' => 1, 'price' => 22.00, 'stock' => 80],
        ];

        foreach ($lenses as $lens) {
            ContactLens::updateOrCreate(['sku' => $lens['sku']], $lens + ['is_active' => true]);
        }
    }
}
