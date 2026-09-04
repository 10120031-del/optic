<?php

namespace Database\Seeders\Demo;

use App\Models\ContactLens;
use App\Models\FaceShape;
use App\Models\Frame;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Widens the starter catalogue into something worth browsing on a projector.
 *
 * CatalogSeeder ships eight frames and four contact lenses — enough to prove
 * the cart works, not enough for a filter sidebar to look like it does
 * anything. This adds the rest of the shelf: thirty-four more frames covering
 * every shape, material, colour and price band the storefront filters on, and
 * ten more contact lenses covering all five replacement schedules.
 *
 * A handful of rows sit at or below the low-stock threshold and two are at
 * zero. Those are not oversights: they are what makes the dashboard's
 * stock-health panel and the out-of-stock badge on a product card visible
 * during a demo.
 *
 * Everything is keyed on SKU through updateOrCreate, so re-running never
 * duplicates a product.
 */
class DemoCatalogSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Styling rule of thumb, the same one CatalogSeeder uses: which face
     * shapes each frame outline is usually recommended for. The face-scan
     * matcher reads the pivot this fills, so a shape missing from here simply
     * never gets recommended.
     *
     * @var array<string, array<int, string>>
     */
    private const RECOMMENDED_FOR = [
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

    public function run(): void
    {
        $this->seedFrames();
        $this->seedContactLenses();
        $this->seedImages();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function frames(): array
    {
        return [
            ['name' => 'Corniche Rectangle', 'brand' => 'Optix', 'sku' => 'FR-1009', 'lens_width' => 55, 'lens_height' => 38, 'bridge_width' => 17, 'temple_length' => 145, 'frame_width' => 140, 'weight_grams' => 21, 'size' => 'medium', 'material' => 'acetate', 'category' => 'eyeglasses', 'type' => 'full_rim', 'shape' => 'rectangle', 'gender' => 'men', 'color' => 'Matte Navy', 'color_hex' => '#1F2A44', 'price' => 92.00, 'stock' => 34, 'manufactured_in' => 'Italy'],
            ['name' => 'Cedar Round', 'brand' => 'Cedar & Co', 'sku' => 'FR-1010', 'lens_width' => 48, 'lens_height' => 44, 'bridge_width' => 21, 'temple_length' => 145, 'frame_width' => 133, 'weight_grams' => 19, 'size' => 'narrow', 'material' => 'metal', 'category' => 'eyeglasses', 'type' => 'full_rim', 'shape' => 'round', 'gender' => 'unisex', 'color' => 'Antique Bronze', 'color_hex' => '#8C6239', 'price' => 78.00, 'stock' => 41, 'manufactured_in' => 'Italy'],
            ['name' => 'Meridian Titan', 'brand' => 'Meridian', 'sku' => 'FR-1011', 'lens_width' => 54, 'lens_height' => 40, 'bridge_width' => 18, 'temple_length' => 148, 'frame_width' => 142, 'weight_grams' => 14, 'size' => 'medium', 'material' => 'titanium', 'category' => 'eyeglasses', 'type' => 'rimless', 'shape' => 'rectangle', 'gender' => 'men', 'color' => 'Brushed Titanium', 'color_hex' => '#8A8D8F', 'price' => 168.00, 'stock' => 16, 'manufactured_in' => 'Japan'],
            ['name' => 'Aurora Cat-Eye', 'brand' => 'Aurora', 'sku' => 'FR-1012', 'lens_width' => 51, 'lens_height' => 43, 'bridge_width' => 16, 'temple_length' => 140, 'frame_width' => 134, 'weight_grams' => 20, 'size' => 'narrow', 'material' => 'acetate', 'category' => 'eyeglasses', 'type' => 'full_rim', 'shape' => 'cat_eye', 'gender' => 'women', 'color' => 'Champagne Crystal', 'color_hex' => '#E3D2B3', 'price' => 112.00, 'stock' => 27, 'manufactured_in' => 'Italy'],
            ['name' => 'Nova Hexagon', 'brand' => 'Nova Optic', 'sku' => 'FR-1013', 'lens_width' => 50, 'lens_height' => 45, 'bridge_width' => 20, 'temple_length' => 145, 'frame_width' => 136, 'weight_grams' => 17, 'size' => 'medium', 'material' => 'metal', 'category' => 'eyeglasses', 'type' => 'full_rim', 'shape' => 'hexagonal', 'gender' => 'unisex', 'color' => 'Rose Gold', 'color_hex' => '#B76E79', 'price' => 98.00, 'stock' => 38, 'manufactured_in' => 'China'],
            ['name' => 'Halcyon Browline', 'brand' => 'Halcyon', 'sku' => 'FR-1014', 'lens_width' => 52, 'lens_height' => 42, 'bridge_width' => 19, 'temple_length' => 145, 'frame_width' => 139, 'weight_grams' => 25, 'size' => 'medium', 'material' => 'mixed', 'category' => 'eyeglasses', 'type' => 'semi_rimless', 'shape' => 'browline', 'gender' => 'men', 'color' => 'Black & Silver', 'color_hex' => '#2B2B2B', 'price' => 104.00, 'stock' => 22, 'manufactured_in' => 'Italy'],
            ['name' => 'Byblos Oval', 'brand' => 'Cedar & Co', 'sku' => 'FR-1015', 'lens_width' => 49, 'lens_height' => 41, 'bridge_width' => 20, 'temple_length' => 140, 'frame_width' => 131, 'weight_grams' => 16, 'size' => 'narrow', 'material' => 'titanium', 'category' => 'eyeglasses', 'type' => 'rimless', 'shape' => 'oval', 'gender' => 'women', 'color' => 'Pale Gold', 'color_hex' => '#D4C08A', 'price' => 145.00, 'stock' => 18, 'manufactured_in' => 'Japan'],
            ['name' => 'Verdun Square', 'brand' => 'Lumina', 'sku' => 'FR-1016', 'lens_width' => 53, 'lens_height' => 45, 'bridge_width' => 18, 'temple_length' => 145, 'frame_width' => 140, 'weight_grams' => 23, 'size' => 'medium', 'material' => 'acetate', 'category' => 'eyeglasses', 'type' => 'full_rim', 'shape' => 'square', 'gender' => 'unisex', 'color' => 'Smoke Grey', 'color_hex' => '#5A5A5A', 'price' => 88.00, 'stock' => 46, 'manufactured_in' => 'Italy'],
            ['name' => 'Batroun Aviator', 'brand' => 'Optix', 'sku' => 'FR-1017', 'lens_width' => 59, 'lens_height' => 51, 'bridge_width' => 15, 'temple_length' => 140, 'frame_width' => 143, 'weight_grams' => 29, 'size' => 'wide', 'material' => 'metal', 'category' => 'sunglasses', 'type' => 'full_rim', 'shape' => 'aviator', 'gender' => 'unisex', 'color' => 'Gunmetal Green', 'color_hex' => '#3E4A42', 'price' => 125.00, 'stock' => 31, 'manufactured_in' => 'Italy'],
            ['name' => 'Solstice Wayfarer', 'brand' => 'Aurora', 'sku' => 'FR-1018', 'lens_width' => 55, 'lens_height' => 46, 'bridge_width' => 18, 'temple_length' => 145, 'frame_width' => 142, 'weight_grams' => 27, 'size' => 'wide', 'material' => 'acetate', 'category' => 'sunglasses', 'type' => 'full_rim', 'shape' => 'wayfarer', 'gender' => 'men', 'color' => 'Gloss Black', 'color_hex' => '#111111', 'price' => 118.00, 'stock' => 44, 'manufactured_in' => 'Italy'],
            ['name' => 'Tyre Oversized', 'brand' => 'Lumina', 'sku' => 'FR-1019', 'lens_width' => 58, 'lens_height' => 52, 'bridge_width' => 16, 'temple_length' => 140, 'frame_width' => 145, 'weight_grams' => 26, 'size' => 'wide', 'material' => 'acetate', 'category' => 'sunglasses', 'type' => 'full_rim', 'shape' => 'geometric', 'gender' => 'women', 'color' => 'Amber Tortoise', 'color_hex' => '#A0522D', 'price' => 132.00, 'stock' => 24, 'manufactured_in' => 'Italy'],
            ['name' => 'Mistral Polar', 'brand' => 'FlexFrame', 'sku' => 'FR-1020', 'lens_width' => 60, 'lens_height' => 48, 'bridge_width' => 14, 'temple_length' => 135, 'frame_width' => 144, 'weight_grams' => 30, 'size' => 'wide', 'material' => 'plastic', 'category' => 'sports', 'type' => 'full_rim', 'shape' => 'geometric', 'gender' => 'men', 'color' => 'Carbon Black', 'color_hex' => '#171717', 'price' => 96.00, 'stock' => 52, 'manufactured_in' => 'China'],
            ['name' => 'Summit Runner', 'brand' => 'FlexFrame', 'sku' => 'FR-1021', 'lens_width' => 57, 'lens_height' => 44, 'bridge_width' => 15, 'temple_length' => 130, 'frame_width' => 138, 'weight_grams' => 24, 'size' => 'medium', 'material' => 'plastic', 'category' => 'sports', 'type' => 'semi_rimless', 'shape' => 'wayfarer', 'gender' => 'unisex', 'color' => 'Volt Yellow', 'color_hex' => '#D6E24A', 'price' => 84.00, 'stock' => 37, 'manufactured_in' => 'China'],
            ['name' => 'Cascade Sport', 'brand' => 'FlexFrame', 'sku' => 'FR-1022', 'lens_width' => 58, 'lens_height' => 45, 'bridge_width' => 16, 'temple_length' => 132, 'frame_width' => 140, 'weight_grams' => 25, 'size' => 'medium', 'material' => 'plastic', 'category' => 'sports', 'type' => 'full_rim', 'shape' => 'rectangle', 'gender' => 'women', 'color' => 'Coral', 'color_hex' => '#F2796A', 'price' => 82.00, 'stock' => 29, 'manufactured_in' => 'China'],
            ['name' => 'Achrafieh Slim', 'brand' => 'Meridian', 'sku' => 'FR-1023', 'lens_width' => 50, 'lens_height' => 38, 'bridge_width' => 19, 'temple_length' => 145, 'frame_width' => 134, 'weight_grams' => 13, 'size' => 'narrow', 'material' => 'titanium', 'category' => 'eyeglasses', 'type' => 'semi_rimless', 'shape' => 'rectangle', 'gender' => 'unisex', 'color' => 'Matte Black', 'color_hex' => '#1A1A1A', 'price' => 155.00, 'stock' => 12, 'manufactured_in' => 'Japan'],
            ['name' => 'Hamra Classic', 'brand' => 'Cedar & Co', 'sku' => 'FR-1024', 'lens_width' => 52, 'lens_height' => 41, 'bridge_width' => 18, 'temple_length' => 145, 'frame_width' => 138, 'weight_grams' => 22, 'size' => 'medium', 'material' => 'acetate', 'category' => 'eyeglasses', 'type' => 'full_rim', 'shape' => 'wayfarer', 'gender' => 'men', 'color' => 'Espresso', 'color_hex' => '#4A2C1A', 'price' => 86.00, 'stock' => 40, 'manufactured_in' => 'Italy'],
            ['name' => 'Zahle Petite', 'brand' => 'Aurora', 'sku' => 'FR-1025', 'lens_width' => 47, 'lens_height' => 39, 'bridge_width' => 18, 'temple_length' => 138, 'frame_width' => 126, 'weight_grams' => 15, 'size' => 'narrow', 'material' => 'metal', 'category' => 'eyeglasses', 'type' => 'full_rim', 'shape' => 'oval', 'gender' => 'women', 'color' => 'Blush Gold', 'color_hex' => '#E6C7B8', 'price' => 94.00, 'stock' => 33, 'manufactured_in' => 'Italy'],
            ['name' => 'Nova Geo', 'brand' => 'Nova Optic', 'sku' => 'FR-1026', 'lens_width' => 52, 'lens_height' => 46, 'bridge_width' => 19, 'temple_length' => 145, 'frame_width' => 139, 'weight_grams' => 20, 'size' => 'medium', 'material' => 'mixed', 'category' => 'eyeglasses', 'type' => 'full_rim', 'shape' => 'geometric', 'gender' => 'unisex', 'color' => 'Crystal Clear', 'color_hex' => '#DCE3E8', 'price' => 90.00, 'stock' => 4, 'manufactured_in' => 'China'],
            ['name' => 'Jounieh Sun', 'brand' => 'Optix', 'sku' => 'FR-1027', 'lens_width' => 56, 'lens_height' => 49, 'bridge_width' => 17, 'temple_length' => 142, 'frame_width' => 141, 'weight_grams' => 26, 'size' => 'medium', 'material' => 'acetate', 'category' => 'sunglasses', 'type' => 'full_rim', 'shape' => 'square', 'gender' => 'women', 'color' => 'Ivory', 'color_hex' => '#EFE7DA', 'price' => 108.00, 'stock' => 21, 'manufactured_in' => 'Italy'],
            ['name' => 'Cedar Aviator Jr', 'brand' => 'Cedar & Co', 'sku' => 'FR-1028', 'lens_width' => 48, 'lens_height' => 42, 'bridge_width' => 15, 'temple_length' => 128, 'frame_width' => 124, 'weight_grams' => 16, 'size' => 'narrow', 'material' => 'metal', 'category' => 'sunglasses', 'type' => 'full_rim', 'shape' => 'aviator', 'gender' => 'kids', 'color' => 'Silver Blue', 'color_hex' => '#9FB6CD', 'price' => 62.00, 'stock' => 35, 'manufactured_in' => 'China'],
            ['name' => 'Little Owl', 'brand' => 'FlexFrame', 'sku' => 'FR-1029', 'lens_width' => 44, 'lens_height' => 37, 'bridge_width' => 15, 'temple_length' => 122, 'frame_width' => 118, 'weight_grams' => 13, 'size' => 'narrow', 'material' => 'plastic', 'category' => 'eyeglasses', 'type' => 'full_rim', 'shape' => 'round', 'gender' => 'kids', 'color' => 'Mint', 'color_hex' => '#A8DCC6', 'price' => 52.00, 'stock' => 48, 'manufactured_in' => 'China'],
            ['name' => 'Pocket Rocket', 'brand' => 'FlexFrame', 'sku' => 'FR-1030', 'lens_width' => 45, 'lens_height' => 36, 'bridge_width' => 16, 'temple_length' => 124, 'frame_width' => 120, 'weight_grams' => 14, 'size' => 'narrow', 'material' => 'plastic', 'category' => 'eyeglasses', 'type' => 'full_rim', 'shape' => 'rectangle', 'gender' => 'kids', 'color' => 'Racing Red', 'color_hex' => '#C0392B', 'price' => 54.00, 'stock' => 42, 'manufactured_in' => 'China'],
            ['name' => 'Meridian Air', 'brand' => 'Meridian', 'sku' => 'FR-1031', 'lens_width' => 51, 'lens_height' => 39, 'bridge_width' => 20, 'temple_length' => 148, 'frame_width' => 137, 'weight_grams' => 11, 'size' => 'medium', 'material' => 'titanium', 'category' => 'eyeglasses', 'type' => 'rimless', 'shape' => 'oval', 'gender' => 'men', 'color' => 'Graphite', 'color_hex' => '#3C3C3C', 'price' => 182.00, 'stock' => 9, 'manufactured_in' => 'Japan'],
            ['name' => 'Halcyon Heritage', 'brand' => 'Halcyon', 'sku' => 'FR-1032', 'lens_width' => 50, 'lens_height' => 44, 'bridge_width' => 21, 'temple_length' => 145, 'frame_width' => 136, 'weight_grams' => 24, 'size' => 'medium', 'material' => 'acetate', 'category' => 'eyeglasses', 'type' => 'full_rim', 'shape' => 'round', 'gender' => 'unisex', 'color' => 'Honey Tortoise', 'color_hex' => '#B5822E', 'price' => 116.00, 'stock' => 26, 'manufactured_in' => 'Italy'],
            ['name' => 'Saida Cat', 'brand' => 'Aurora', 'sku' => 'FR-1033', 'lens_width' => 53, 'lens_height' => 44, 'bridge_width' => 17, 'temple_length' => 142, 'frame_width' => 138, 'weight_grams' => 21, 'size' => 'medium', 'material' => 'acetate', 'category' => 'sunglasses', 'type' => 'full_rim', 'shape' => 'cat_eye', 'gender' => 'women', 'color' => 'Deep Plum', 'color_hex' => '#5B2C4A', 'price' => 122.00, 'stock' => 19, 'manufactured_in' => 'Italy'],
            ['name' => 'Nova Wire', 'brand' => 'Nova Optic', 'sku' => 'FR-1034', 'lens_width' => 47, 'lens_height' => 47, 'bridge_width' => 22, 'temple_length' => 145, 'frame_width' => 132, 'weight_grams' => 15, 'size' => 'narrow', 'material' => 'metal', 'category' => 'eyeglasses', 'type' => 'full_rim', 'shape' => 'round', 'gender' => 'unisex', 'color' => 'Gold Wire', 'color_hex' => '#CBA135', 'price' => 74.00, 'stock' => 0, 'manufactured_in' => 'China'],
            ['name' => 'Tripoli Bold', 'brand' => 'Lumina', 'sku' => 'FR-1035', 'lens_width' => 56, 'lens_height' => 47, 'bridge_width' => 17, 'temple_length' => 148, 'frame_width' => 144, 'weight_grams' => 28, 'size' => 'wide', 'material' => 'acetate', 'category' => 'eyeglasses', 'type' => 'full_rim', 'shape' => 'square', 'gender' => 'men', 'color' => 'Midnight Blue', 'color_hex' => '#0F1B3C', 'price' => 102.00, 'stock' => 30, 'manufactured_in' => 'Italy'],
            ['name' => 'Aley Reader', 'brand' => 'Cedar & Co', 'sku' => 'FR-1036', 'lens_width' => 51, 'lens_height' => 36, 'bridge_width' => 19, 'temple_length' => 140, 'frame_width' => 135, 'weight_grams' => 18, 'size' => 'medium', 'material' => 'metal', 'category' => 'eyeglasses', 'type' => 'semi_rimless', 'shape' => 'rectangle', 'gender' => 'unisex', 'color' => 'Warm Copper', 'color_hex' => '#A66A3A', 'price' => 68.00, 'stock' => 47, 'manufactured_in' => 'China'],
            ['name' => 'Broummana Fog', 'brand' => 'Halcyon', 'sku' => 'FR-1037', 'lens_width' => 54, 'lens_height' => 43, 'bridge_width' => 18, 'temple_length' => 145, 'frame_width' => 141, 'weight_grams' => 22, 'size' => 'medium', 'material' => 'mixed', 'category' => 'eyeglasses', 'type' => 'full_rim', 'shape' => 'hexagonal', 'gender' => 'women', 'color' => 'Slate Rose', 'color_hex' => '#9E7B7B', 'price' => 99.00, 'stock' => 3, 'manufactured_in' => 'Italy'],
            ['name' => 'Baalbek Stone', 'brand' => 'Cedar & Co', 'sku' => 'FR-1038', 'lens_width' => 55, 'lens_height' => 42, 'bridge_width' => 18, 'temple_length' => 148, 'frame_width' => 143, 'weight_grams' => 27, 'size' => 'wide', 'material' => 'acetate', 'category' => 'eyeglasses', 'type' => 'full_rim', 'shape' => 'browline', 'gender' => 'men', 'color' => 'Sandstone', 'color_hex' => '#C2B280', 'price' => 97.00, 'stock' => 23, 'manufactured_in' => 'Italy'],
            ['name' => 'Aurora Mirage', 'brand' => 'Aurora', 'sku' => 'FR-1039', 'lens_width' => 57, 'lens_height' => 50, 'bridge_width' => 16, 'temple_length' => 140, 'frame_width' => 142, 'weight_grams' => 25, 'size' => 'wide', 'material' => 'metal', 'category' => 'sunglasses', 'type' => 'rimless', 'shape' => 'geometric', 'gender' => 'women', 'color' => 'Mirror Pink', 'color_hex' => '#E8A0B4', 'price' => 138.00, 'stock' => 17, 'manufactured_in' => 'Italy'],
            ['name' => 'Meridian Executive', 'brand' => 'Meridian', 'sku' => 'FR-1040', 'lens_width' => 55, 'lens_height' => 40, 'bridge_width' => 18, 'temple_length' => 150, 'frame_width' => 143, 'weight_grams' => 15, 'size' => 'wide', 'material' => 'titanium', 'category' => 'eyeglasses', 'type' => 'full_rim', 'shape' => 'rectangle', 'gender' => 'men', 'color' => 'Ink Black', 'color_hex' => '#141414', 'price' => 195.00, 'stock' => 8, 'manufactured_in' => 'Japan'],
            ['name' => 'Nabatieh Everyday', 'brand' => 'Optix', 'sku' => 'FR-1041', 'lens_width' => 52, 'lens_height' => 42, 'bridge_width' => 19, 'temple_length' => 145, 'frame_width' => 138, 'weight_grams' => 20, 'size' => 'medium', 'material' => 'plastic', 'category' => 'eyeglasses', 'type' => 'full_rim', 'shape' => 'oval', 'gender' => 'unisex', 'color' => 'Warm Grey', 'color_hex' => '#8E8B85', 'price' => 64.00, 'stock' => 55, 'manufactured_in' => 'China'],
            ['name' => 'Nova Studio', 'brand' => 'Nova Optic', 'sku' => 'FR-1042', 'lens_width' => 53, 'lens_height' => 45, 'bridge_width' => 18, 'temple_length' => 145, 'frame_width' => 140, 'weight_grams' => 19, 'size' => 'medium', 'material' => 'mixed', 'category' => 'eyeglasses', 'type' => 'semi_rimless', 'shape' => 'cat_eye', 'gender' => 'women', 'color' => 'Olive Crystal', 'color_hex' => '#6B7A4B', 'price' => 106.00, 'stock' => 28, 'manufactured_in' => 'Italy'],
        ];
    }

    private function seedFrames(): void
    {
        $faceShapes = FaceShape::pluck('id', 'slug');

        foreach ($this->frames() as $frame) {
            $frame['description'] = sprintf(
                '%s by %s — a %s %s %s frame in %s. %smm lens width, %sg on the nose.',
                $frame['name'],
                $frame['brand'],
                str_replace('_', ' ', $frame['shape']),
                $frame['material'],
                str_replace('_', ' ', $frame['type']),
                strtolower($frame['color']),
                $frame['lens_width'],
                $frame['weight_grams'],
            );

            $record = Frame::updateOrCreate(['sku' => $frame['sku']], $frame + ['is_active' => true]);

            $record->faceShapes()->sync(
                collect(self::RECOMMENDED_FOR[$frame['shape']] ?? [])
                    ->map(fn (string $slug) => $faceShapes[$slug] ?? null)
                    ->filter()
                    ->values()
                    ->all()
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function contactLenses(): array
    {
        return [
            ['name' => 'DailyClear Plus', 'brand' => 'VisionPlus', 'sku' => 'CL-2005', 'type' => 'daily', 'material' => 'silicone_hydrogel', 'diameter' => 14.10, 'base_curve' => 8.50, 'pack_size' => 90, 'expiry_months' => null, 'price' => 78.00, 'stock' => 140, 'description' => 'Ninety-pack of daily disposables for full-time wearers — no cleaning, no case, a fresh pair every morning.'],
            ['name' => 'AquaSoft Daily', 'brand' => 'ClearView', 'sku' => 'CL-2006', 'type' => 'daily', 'material' => 'hydrogel', 'diameter' => 14.20, 'base_curve' => 8.60, 'pack_size' => 30, 'expiry_months' => null, 'price' => 26.00, 'stock' => 180, 'description' => 'High-water-content daily lens that stays comfortable through a long day at a screen.'],
            ['name' => 'OxyBreathe Monthly', 'brand' => 'Lensa', 'sku' => 'CL-2007', 'type' => 'monthly', 'material' => 'silicone_hydrogel', 'diameter' => 14.00, 'base_curve' => 8.40, 'pack_size' => 6, 'expiry_months' => 1, 'price' => 34.00, 'stock' => 110, 'description' => 'High oxygen transmissibility, for wearers who keep their lenses in from morning to night.'],
            ['name' => 'FlexWear Bi-Weekly', 'brand' => 'Lensa', 'sku' => 'CL-2008', 'type' => 'biweekly', 'material' => 'silicone_hydrogel', 'diameter' => 14.20, 'base_curve' => 8.70, 'pack_size' => 12, 'expiry_months' => null, 'price' => 30.00, 'stock' => 95, 'description' => 'A fortnightly replacement schedule — the middle ground between daily convenience and monthly value.'],
            ['name' => 'ColorPop Honey', 'brand' => 'ClearView', 'sku' => 'CL-2009', 'type' => 'monthly', 'material' => 'hydrogel', 'color' => 'Honey', 'diameter' => 14.20, 'base_curve' => 8.60, 'pack_size' => 2, 'expiry_months' => 1, 'price' => 24.00, 'stock' => 70, 'description' => 'Warm honey tint with a soft limbal ring, available plano or with power.'],
            ['name' => 'ColorPop Emerald', 'brand' => 'ClearView', 'sku' => 'CL-2010', 'type' => 'monthly', 'material' => 'hydrogel', 'color' => 'Emerald', 'diameter' => 14.20, 'base_curve' => 8.60, 'pack_size' => 2, 'expiry_months' => 1, 'price' => 24.00, 'stock' => 8, 'description' => 'Deep green tint that still reads as natural on dark eyes in daylight.'],
            ['name' => 'ColorPop Hazel', 'brand' => 'ClearView', 'sku' => 'CL-2011', 'type' => 'monthly', 'material' => 'hydrogel', 'color' => 'Hazel', 'diameter' => 14.00, 'base_curve' => 8.50, 'pack_size' => 2, 'expiry_months' => 1, 'price' => 24.00, 'stock' => 64, 'description' => 'Hazel blend with a graduated edge, for a subtle change of colour rather than an obvious one.'],
            ['name' => 'ToricVision Monthly', 'brand' => 'VisionPlus', 'sku' => 'CL-2012', 'type' => 'monthly', 'material' => 'silicone_hydrogel', 'diameter' => 14.50, 'base_curve' => 8.70, 'pack_size' => 6, 'expiry_months' => 1, 'price' => 46.00, 'stock' => 58, 'description' => 'Toric lens for astigmatism, stabilised so the cylinder axis stays where it belongs when you blink.'],
            ['name' => 'MultiFocal Comfort', 'brand' => 'Lensa', 'sku' => 'CL-2013', 'type' => 'monthly', 'material' => 'silicone_hydrogel', 'diameter' => 14.30, 'base_curve' => 8.60, 'pack_size' => 6, 'expiry_months' => 1, 'price' => 52.00, 'stock' => 42, 'description' => 'Multifocal design for presbyopia — near, intermediate and distance without reaching for readers.'],
            ['name' => 'ExtendedWear Yearly', 'brand' => 'VisionPlus', 'sku' => 'CL-2014', 'type' => 'yearly', 'material' => 'hydrogel', 'diameter' => 14.00, 'base_curve' => 8.60, 'pack_size' => 2, 'expiry_months' => 12, 'price' => 60.00, 'stock' => 6, 'description' => 'Conventional annual-replacement lens, cleaned and stored nightly. The most economical option per year.'],
        ];
    }

    private function seedContactLenses(): void
    {
        foreach ($this->contactLenses() as $lens) {
            ContactLens::updateOrCreate(['sku' => $lens['sku']], $lens + ['is_active' => true]);
        }
    }

    /**
     * Give every frame a primary photo, cycling through whatever real uploads
     * are sitting on the public disk.
     *
     * A grid where nine cards in ten fall back to the grey placeholder looks
     * broken on a projector even though nothing is wrong. Cycling the few real
     * files the shop has means the storefront reads as a shop.
     *
     * See DemoImages for which uploads qualify and why. With none on the disk
     * this does nothing at all rather than writing rows pointing at files that
     * are not there. Frames that already carry a sort_order 0 image are
     * skipped, so a staff upload is never overwritten.
     */
    private function seedImages(): void
    {
        $available = DemoImages::productShots();

        if ($available === []) {
            $this->command?->warn('  No usable product photos in storage/app/public/frames — cards will show the placeholder.');

            return;
        }

        $index = 0;

        foreach (Frame::orderBy('id')->get() as $frame) {
            if ($frame->images()->where('sort_order', 0)->exists()) {
                continue;
            }

            $frame->images()->create([
                'path' => $available[$index % count($available)],
                'alt_text' => "{$frame->name} front view",
                'sort_order' => 0,
                'is_primary' => true,
            ]);

            $index++;
        }
    }
}
