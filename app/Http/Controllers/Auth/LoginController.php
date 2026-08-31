<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, CartService $carts): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Those credentials don\'t match an account we have on file.',
            ]);
        }

        $request->session()->regenerate();

        // Staff and delivery logs go straight to their work consoles; they have
        // no cart to carry over and the customer-facing account pages are closed to them.
        if ($request->user()->isOwner() || $request->user()->isStaff()) {
            return redirect()->intended(route('admin.dashboard'));
        }

        if ($request->user()->isDelivery()) {
            return redirect()->intended(route('delivery.orders.index'));
        }

        $carts->mergeSessionCartIntoUser($request, $request->user());

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
