<?php

namespace App\Services;

use App\Models\CartContactLens;
use App\Models\CartEyeglass;
use Illuminate\Support\Collection;

class PricingService
{
    /**
     * The "proposed price" for one configured eyeglass cart line: frame +
     * lens package + every selected composable feature (anti-blue, UV,
     * photochromic...), times quantity. This is the single formula behind
     * requirement 4 — everything else in the cart/checkout flow just calls
     * this and totals the results.
     */
    public function eyeglassLineUnitPrice(CartEyeglass $line): float
    {
        $featuresTotal = $line->features->sum(fn ($feature) => (float) $feature->price);

        return round((float) $line->frame->price + (float) $line->lens->price + $featuresTotal, 2);
    }

    public function eyeglassLineTotal(CartEyeglass $line): float
    {
        return round($this->eyeglassLineUnitPrice($line) * $line->quantity, 2);
    }

    public function contactLensLineUnitPrice(CartContactLens $line): float
    {
        return round((float) $line->contactLens->price, 2);
    }

    public function contactLensLineTotal(CartContactLens $line): float
    {
        return round($this->contactLensLineUnitPrice($line) * $line->quantity, 2);
    }

    /**
     * Sum of every line in a cart, before shipping/tax.
     */
    public function cartSubtotal(Collection $eyeglassLines, Collection $contactLensLines): float
    {
        $eyeglasses = $eyeglassLines->sum(fn (CartEyeglass $line) => $this->eyeglassLineTotal($line));
        $contacts = $contactLensLines->sum(fn (CartContactLens $line) => $this->contactLensLineTotal($line));

        return round($eyeglasses + $contacts, 2);
    }

    /**
     * Flat-rate shipping + a simple percentage tax. Swap for a real
     * shipping-zone/tax-rule lookup when those tables exist.
     */
    public function orderTotals(float $subtotal, float $shippingCost = 0.0, float $taxRate = 0.0): array
    {
        $tax = round($subtotal * $taxRate, 2);
        $total = round($subtotal + $shippingCost + $tax, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'shipping_cost' => round($shippingCost, 2),
            'tax' => $tax,
            'total' => $total,
        ];
    }
}
