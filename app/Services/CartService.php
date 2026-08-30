<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Http\Request;

class CartService
{
    /**
     * The cart for whoever is making the request — a signed-in user's cart,
     * or a guest cart keyed to their session id (see carts.session_id).
     * Creates one on first touch so callers never have to null-check.
     */
    public function current(Request $request): Cart
    {
        if ($request->user()) {
            return Cart::firstOrCreate(['user_id' => $request->user()->id]);
        }

        return Cart::firstOrCreate(['session_id' => $request->session()->getId()]);
    }

    /**
     * Fold a guest cart into the user's cart right after login/register, so
     * items added before signing in aren't lost. If the user already has a
     * cart, the guest lines are moved over and the empty guest cart is
     * dropped; otherwise the guest cart is simply claimed by the user.
     */
    public function mergeSessionCartIntoUser(Request $request, User $user): void
    {
        $sessionCart = Cart::where('session_id', $request->session()->getId())->first();

        if (! $sessionCart) {
            return;
        }

        $userCart = Cart::where('user_id', $user->id)->first();

        if (! $userCart) {
            $sessionCart->update(['user_id' => $user->id, 'session_id' => null]);

            return;
        }

        $sessionCart->eyeglasses()->update(['cart_id' => $userCart->id]);
        $sessionCart->contactLenses()->update(['cart_id' => $userCart->id]);
        $sessionCart->delete();
    }
}
