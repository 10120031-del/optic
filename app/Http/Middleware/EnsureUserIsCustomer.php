<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsCustomer
{
    /**
     * Keep staff out of customer-only shopping flows (cart, checkout, orders,
     * prescriptions, reviews). An admin account has no cart and places no
     * orders, so these pages would only ever show them an empty account.
     *
     * Guests pass through untouched — they can shop before signing in. This
     * is deliberately not applied to the public catalog: staff still browse
     * the storefront to check how a product they just edited renders.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isAdmin()) {
            return redirect()
                ->route('admin.dashboard')
                ->with('status', __('Shopping and account pages are for customers. You are signed in as staff.'));
        }

        return $next($request);
    }
}
