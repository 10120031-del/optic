<?php

namespace Database\Seeders;

use App\Models\FaceShape;
use Illuminate\Database\Seeder;

class FaceShapeSeeder extends Seeder
{
    /**
     * The standard six face shapes used by stylists and, later, by the
     * face-scan AI classifier's output.
     */
    public function run(): void
    {
        $shapes = [
            ['name' => 'Oval', 'slug' => 'oval', 'description' => 'Balanced proportions, slightly wider at the cheekbones. Most frame shapes suit it.'],
            ['name' => 'Round', 'slug' => 'round', 'description' => 'Soft curves, similar width and length. Angular frames add definition.'],
            ['name' => 'Square', 'slug' => 'square', 'description' => 'Strong jawline and forehead of similar width. Round or oval frames soften the angles.'],
            ['name' => 'Heart', 'slug' => 'heart', 'description' => 'Wider forehead narrowing to the chin. Frames wider at the bottom balance it.'],
            ['name' => 'Diamond', 'slug' => 'diamond', 'description' => 'Narrow forehead and jawline, wide cheekbones. Oval or cat-eye frames flatter it.'],
            ['name' => 'Oblong', 'slug' => 'oblong', 'description' => 'Longer than it is wide. Frames with more depth or decorative temples shorten it visually.'],
        ];

        foreach ($shapes as $shape) {
            FaceShape::updateOrCreate(['slug' => $shape['slug']], $shape);
        }
    }
}
