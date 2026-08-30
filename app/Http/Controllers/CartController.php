<?php

namespace App\Http\Controllers;

use App\Models\CartContactLens;
use App\Models\CartEyeglass;
use App\Models\ContactLens;
use App\Models\Frame;
use App\Models\Lens;
use App\Models\Prescription;
use App\Services\CartService;
use App\Services\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
        private readonly PricingService $pricing,
    ) {
    }

    public function index(Request $request): View
    {
        $cart = $this->carts->current($request);
        $cart->load([
            'eyeglasses.frame',
            'eyeglasses.lens',
            'eyeglasses.features',
            'eyeglasses.prescription',
            'contactLenses.contactLens',
        ]);

        $eyeglassTotal = $cart->eyeglasses->sum(fn (CartEyeglass $line) => $this->pricing->eyeglassLineTotal($line));
        $contactLensTotal = $cart->contactLenses->sum(fn (CartContactLens $line) => $this->pricing->contactLensLineTotal($line));

        return view('cart.index', [
            'cart' => $cart,
            'pricing' => $this->pricing,
            'subtotal' => round($eyeglassTotal + $contactLensTotal, 2),
        ]);
    }

    /**
     * Add a configured eyeglass: frame + lens package + any composable
     * features (anti-blue, UV, photochromic...) + prescription. This is
     * requirement 4's "composite lens" selection landing in the cart.
     */
    public function storeEyeglass(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'frame_id' => ['required', 'exists:frames,id'],
            'lens_id' => ['required', 'exists:lenses,id'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'feature_ids' => ['sometimes', 'array'],
            'feature_ids.*' => ['exists:lens_features,id'],
            'prescription_id' => ['nullable', 'exists:prescriptions,id'],
            'left_sphere' => ['nullable', 'numeric'],
            'left_cylinder' => ['nullable', 'numeric'],
            'left_axis' => ['nullable', 'integer', 'between:0,180'],
            'left_add' => ['nullable', 'numeric'],
            'right_sphere' => ['nullable', 'numeric'],
            'right_cylinder' => ['nullable', 'numeric'],
            'right_axis' => ['nullable', 'integer', 'between:0,180'],
            'right_add' => ['nullable', 'numeric'],
            'pd' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $frame = Frame::where('is_active', true)->findOrFail($data['frame_id']);
        $lens = Lens::where('is_active', true)->findOrFail($data['lens_id']);

        // Only features actually offered on this lens package are allowed,
        // even if a crafted request asks for others.
        $allowedFeatureIds = $lens->features()->pluck('lens_features.id');
        $featureIds = collect($data['feature_ids'] ?? [])->intersect($allowedFeatureIds)->values();

        $prescriptionId = $data['prescription_id'] ?? null;

        if ($prescriptionId !== null) {
            // Prescriptions are only ever owned by signed-in users, so a
            // guest submitting a prescription_id is never valid.
            abort_unless(
                $request->user() && Prescription::where('id', $prescriptionId)->where('user_id', $request->user()->id)->exists(),
                403
            );
        }

        $cart = $this->carts->current($request);

        $line = $cart->eyeglasses()->create([
            'frame_id' => $frame->id,
            'lens_id' => $lens->id,
            'prescription_id' => $data['prescription_id'] ?? null,
            'quantity' => $data['quantity'] ?? 1,
            'left_sphere' => $data['left_sphere'] ?? null,
            'left_cylinder' => $data['left_cylinder'] ?? null,
            'left_axis' => $data['left_axis'] ?? null,
            'left_add' => $data['left_add'] ?? null,
            'right_sphere' => $data['right_sphere'] ?? null,
            'right_cylinder' => $data['right_cylinder'] ?? null,
            'right_axis' => $data['right_axis'] ?? null,
            'right_add' => $data['right_add'] ?? null,
            'pd' => $data['pd'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $line->features()->sync($featureIds);

        return back()->with('status', 'Added to cart.');
    }

    public function updateEyeglass(Request $request, CartEyeglass $eyeglass): RedirectResponse
    {
        $this->authorizeCartLine($request, $eyeglass->cart_id);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'feature_ids' => ['sometimes', 'array'],
            'feature_ids.*' => ['exists:lens_features,id'],
        ]);

        $eyeglass->update(['quantity' => $data['quantity']]);

        if (array_key_exists('feature_ids', $data)) {
            $allowedFeatureIds = $eyeglass->lens->features()->pluck('lens_features.id');
            $eyeglass->features()->sync(collect($data['feature_ids'])->intersect($allowedFeatureIds));
        }

        return back()->with('status', 'Cart updated.');
    }

    public function destroyEyeglass(Request $request, CartEyeglass $eyeglass): RedirectResponse
    {
        $this->authorizeCartLine($request, $eyeglass->cart_id);
        $eyeglass->delete();

        return back()->with('status', 'Removed from cart.');
    }

    public function storeContactLens(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'contact_lens_id' => ['required', 'exists:contact_lenses,id'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'left_power' => ['nullable', 'numeric'],
            'right_power' => ['nullable', 'numeric'],
            'left_cylinder' => ['nullable', 'numeric'],
            'right_cylinder' => ['nullable', 'numeric'],
            'left_axis' => ['nullable', 'integer', 'between:0,180'],
            'right_axis' => ['nullable', 'integer', 'between:0,180'],
        ]);

        $contactLens = ContactLens::where('is_active', true)->findOrFail($data['contact_lens_id']);
        $cart = $this->carts->current($request);

        $cart->contactLenses()->create([
            'contact_lens_id' => $contactLens->id,
            'quantity' => $data['quantity'] ?? 1,
            'left_power' => $data['left_power'] ?? null,
            'right_power' => $data['right_power'] ?? null,
            'left_cylinder' => $data['left_cylinder'] ?? null,
            'right_cylinder' => $data['right_cylinder'] ?? null,
            'left_axis' => $data['left_axis'] ?? null,
            'right_axis' => $data['right_axis'] ?? null,
        ]);

        return back()->with('status', 'Added to cart.');
    }

    public function updateContactLens(Request $request, CartContactLens $contactLens): RedirectResponse
    {
        $this->authorizeCartLine($request, $contactLens->cart_id);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $contactLens->update(['quantity' => $data['quantity']]);

        return back()->with('status', 'Cart updated.');
    }

    public function destroyContactLens(Request $request, CartContactLens $contactLens): RedirectResponse
    {
        $this->authorizeCartLine($request, $contactLens->cart_id);
        $contactLens->delete();

        return back()->with('status', 'Removed from cart.');
    }

    /**
     * Make sure the cart line being touched actually belongs to whoever is
     * asking — the guest session or the signed-in user's cart — since cart
     * line routes are plain resource routes without a nested cart_id.
     */
    private function authorizeCartLine(Request $request, int $cartId): void
    {
        abort_unless($this->carts->current($request)->id === $cartId, 403);
    }
}
