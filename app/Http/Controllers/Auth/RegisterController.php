<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request, CartService $carts): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone_number' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'confirmed', 'min:8'],
            'newsletter_opt_in' => ['sometimes', 'boolean'],
        ]);

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'] ?? null,
            'password' => Hash::make($data['password']),
            'newsletter_opt_in' => $request->boolean('newsletter_opt_in', true),
        ]);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();
        $carts->mergeSessionCartIntoUser($request, $user);

        // The Registered event above has already mailed a confirmation link
        // (User implements MustVerifyEmail). Land them on the page that says
        // so — it's the only place that explains the email they're about to
        // get, and it has a "keep browsing" way out, so nothing is blocked.
        return redirect()->route('verification.notice');
    }
}
