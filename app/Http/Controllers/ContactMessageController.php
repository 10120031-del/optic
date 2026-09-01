<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactMessageController extends Controller
{
    /**
     * Take an enquiry from the About page's contact form.
     *
     * Open to visitors — a shopper deciding whether to buy has no account
     * yet, and forcing one on them is exactly how an enquiry is lost. The
     * route is rate-limited (see routes/web.php) since it is unauthenticated,
     * and the row lands in the staff console through ContactMessageObserver.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'topic' => ['required', 'string', 'in:'.implode(',', array_keys(ContactMessage::TOPICS))],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
            // Honeypot: a field no human sees, so anything in it came from a
            // bot filling every input on the page. Silently accepted below
            // rather than rejected, so the bot has nothing to tune against.
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        if (filled($data['website'] ?? null)) {
            return $this->confirmation();
        }

        ContactMessage::create([
            'user_id' => $request->user()?->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'topic' => $data['topic'],
            'message' => $data['message'],
            'status' => ContactMessage::STATUS_NEW,
        ]);

        return $this->confirmation();
    }

    /**
     * Back to the contact section with the receipt.
     *
     * Deliberately not the shared 'status' key the layout's <x-flash /> reads:
     * that banner sits at the very top of the page, and the redirect drops the
     * sender at #contact — two screens below it. The About page renders this
     * key inside the contact panel instead, where they are actually looking.
     */
    private function confirmation(): RedirectResponse
    {
        return redirect()
            ->route('about')
            ->withFragment('contact')
            ->with('contact_status', __('Thanks — your message is with us. We usually reply within one business day.'));
    }
}
