<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Gate every admin route behind an authenticated admin user. Applied
     * as the 'admin' middleware alias (registered in bootstrap/app.php),
     * always alongside 'auth' so guests get redirected to login first.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isEmployee()) {
            abort(403, 'This area is for shop staff only.');
        }

        return $next($request);
    }
}
