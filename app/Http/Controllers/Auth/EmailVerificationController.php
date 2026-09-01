<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    /**
     * The "check your inbox" page. Also where the `verified` middleware sends
     * anyone who reaches a gated page without having confirmed their address.
     */
    public function notice(Request $request): View|RedirectResponse
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->route('home')
            : view('auth.verify-email');
    }

    /**
     * The link target. EmailVerificationRequest checks the signature, that the
     * {id} is the signed-in user, and that the {hash} still matches their
     * current address — so a link mailed to an address that has since changed
     * no longer verifies anything.
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        // Re-clicking a link that already worked is common enough (mail
        // clients prefetch, people press back) that it shouldn't look like a
        // failure.
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('home')
                ->with('status', __('Your email address is already confirmed.'));
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        // Home, not redirect()->intended(). Nothing is gated on verification,
        // so the session's intended URL here is just wherever this person last
        // bumped into a login wall — which for a customer who once tried
        // /admin is a 403 page to land on straight after confirming.
        return redirect()->route('home')
            ->with('status', __('Thanks — your email address is confirmed.'));
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('home');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', __('A fresh confirmation link is on its way to :email.', [
            'email' => $request->user()->email,
        ]));
    }
}
