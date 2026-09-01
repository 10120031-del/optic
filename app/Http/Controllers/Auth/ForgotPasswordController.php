<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        // Being throttled is worth saying out loud — it tells someone who
        // clicked twice that a link really is on the way and they should wait
        // for it rather than keep hammering the form.
        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => __('A reset link went out moments ago. Please wait a minute before asking for another.'),
            ]);
        }

        // Every other outcome gets the same answer, including "no such user".
        // Saying which addresses have accounts would turn this form into a way
        // to enumerate the shop's customer list.
        return back()->with('status', __('If that address has an account, a reset link is on its way to it.'));
    }
}
