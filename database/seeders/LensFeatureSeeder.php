<?php

namespace Database\Seeders;

use App\Models\LensFeature;
use Illuminate\Database\Seeder;

class LensFeatureSeeder extends Seeder
{
    /**
     * Add-on coatings/treatments the customer can compose on top of a lens
     * package at checkout (requirement: "anti blue", "darkens on UV", etc.).
     * These are independent booleans, not mutually exclusive, so a customer
     * can stack several of them.
     */
    public function run(): void
    {
        $features = [
            ['name' => 'Anti-Blue Light', 'slug' => 'anti-blue-light', 'price' => 15.00, 'description' => 'Filters blue light from screens to reduce digital eye strain.'],
            ['name' => 'UV Protection', 'slug' => 'uv-protection', 'price' => 10.00, 'description' => 'Blocks 100% of UVA/UVB rays.'],
            ['name' => 'Photochromic (Darkens in Sunlight)', 'slug' => 'photochromic', 'price' => 35.00, 'description' => 'Automatically darkens outdoors and clears back up indoors.'],
            ['name' => 'Anti-Reflective Coating', 'slug' => 'anti-reflective-coating', 'price' => 20.00, 'description' => 'Reduces glare and reflections for clearer vision and better aesthetics.'],
            ['name' => 'Scratch-Resistant Coating', 'slug' => 'scratch-resistant-coating', 'price' => 12.00, 'description' => 'A hard coat that extends lens life.'],
            ['name' => 'Polarized', 'slug' => 'polarized', 'price' => 25.00, 'description' => 'Cuts glare off flat, reflective surfaces such as water and roads.'],
        ];

        foreach ($features as $feature) {
            LensFeature::updateOrCreate(['slug' => $feature['slug']], $feature);
        }
    }
}
