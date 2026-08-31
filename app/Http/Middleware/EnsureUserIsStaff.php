<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isStaff() && ! $request->user()->isOwner()) {
            abort(403, 'This area is for shop staff only.');
        }

        return $next($request);
    }
}
