<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsOwner
{
    /**
     * The owner has the full control over the business: pricing, promotions,
     * staffing, and delivery assignment.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isOwner()) {
            abort(403, 'This area is for the shop owner only.');
        }

        return $next($request);
    }
}
