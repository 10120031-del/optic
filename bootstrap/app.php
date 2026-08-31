<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsCustomer;
use App\Http\Middleware\EnsureUserIsDelivery;
use App\Http\Middleware\EnsureUserIsOwner;
use App\Http\Middleware\EnsureUserIsStaff;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'owner' => EnsureUserIsOwner::class,
            'staff' => EnsureUserIsStaff::class,
            'delivery' => EnsureUserIsDelivery::class,
            'customer' => EnsureUserIsCustomer::class,
        ]);

        // In production the app sits behind a reverse proxy / load balancer
        // terminating TLS. Without this, Laravel sees the plain HTTP hop from
        // the proxy and builds http:// URLs on an https:// site (mixed
        // content, broken redirects) and logs the proxy's IP for every visitor.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A stale CSRF token on sign-out usually means the session already
        // expired or the page was cached after a previous logout. Treat it
        // as a successful sign-out instead of showing the 419 page.
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if (! $request->is('logout')) {
                return null;
            }

            Auth::logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()->route('home');
        });
    })->create();
