<?php

namespace Tests\Unit;

use App\Models\CartEyeglass;
use App\Models\Frame;
use App\Models\Lens;
use App\Models\LensFeature;
use App\Services\PricingService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for the one place a price is decided. Nothing here touches the
 * database: the cart line is built in memory and its relations are handed to
 * it directly, so a failure here means the formula is wrong, not the schema.
 */
class PricingServiceTest extends TestCase
{
    private PricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricing = new PricingService;
    }

    /** Build an unsaved cart line with its relations pre-loaded. */
    private function line(float $frame, float $lens, array $features = [], int $quantity = 1): CartEyeglass
    {
        $line = new CartEyeglass(['quantity' => $quantity]);
        $line->setRelation('frame', new Frame(['price' => $frame]));
        $line->setRelation('lens', new Lens(['price' => $lens]));
        $line->setRelation('features', collect($features)->map(
            fn (float $price) => new LensFeature(['price' => $price])
        ));

        return $line;
    }

    #[Test]
    public function unit_price_is_frame_plus_lens_plus_every_feature(): void
    {
        $line = $this->line(frame: 89.00, lens: 45.50, features: [15.00, 9.99]);

        $this->assertSame(159.49, $this->pricing->eyeglassLineUnitPrice($line));
    }

    #[Test]
    public function a_line_with_no_features_is_just_frame_plus_lens(): void
    {
        $this->assertSame(134.50, $this->pricing->eyeglassLineUnitPrice($this->line(89.00, 45.50)));
    }

    #[Test]
    public function line_total_multiplies_the_unit_price_by_quantity(): void
    {
        $line = $this->line(frame: 89.00, lens: 45.50, features: [15.00], quantity: 3);

        $this->assertSame(448.50, $this->pricing->eyeglassLineTotal($line));
    }

    #[Test]
    #[DataProvider('totalsProvider')]
    public function order_totals_add_shipping_and_tax(float $subtotal, float $shipping, float $rate, array $expected): void
    {
        $this->assertSame($expected, $this->pricing->orderTotals($subtotal, $shipping, $rate));
    }

    public static function totalsProvider(): array
    {
        return [
            'free shipping, no tax' => [100.00, 0.0, 0.0,
                ['subtotal' => 100.00, 'shipping_cost' => 0.00, 'tax' => 0.00, 'total' => 100.00]],
            'flat shipping + 11% VAT' => [200.00, 5.00, 0.11,
                ['subtotal' => 200.00, 'shipping_cost' => 5.00, 'tax' => 22.00, 'total' => 227.00]],
            'tax rounds to the cent' => [33.33, 0.0, 0.11,
                ['subtotal' => 33.33, 'shipping_cost' => 0.00, 'tax' => 3.67, 'total' => 37.00]],
            'empty cart' => [0.0, 0.0, 0.11,
                ['subtotal' => 0.00, 'shipping_cost' => 0.00, 'tax' => 0.00, 'total' => 0.00]],
        ];
    }
}
