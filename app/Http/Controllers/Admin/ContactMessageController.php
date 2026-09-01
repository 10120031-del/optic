<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The staff side of the About page's contact form: read what came in, mark
 * it handled, delete the spam. There is no reply-from-here action on
 * purpose — staff answer from their own mail client (the list gives them a
 * mailto link), so nothing pretends the shop has an outbound support desk
 * it does not have.
 */
class ContactMessageController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->get('filter', 'open');

        $messages = ContactMessage::query()
            ->with(['user', 'handler'])
            ->when($filter === 'open', fn ($q) => $q->whereIn('status', [ContactMessage::STATUS_NEW, ContactMessage::STATUS_READ]))
            ->when($filter === 'new', fn ($q) => $q->unhandled())
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.messages.index', [
            'messages' => $messages,
            'filter' => $filter,
        ]);
    }

    public function updateStatus(Request $request, ContactMessage $message): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:new,read,closed'],
        ]);

        $message->update([
            'status' => $data['status'],
            // Who last touched it, and when — cleared if it is pushed back to
            // "new" so the row reads as genuinely untouched again.
            'handled_by' => $data['status'] === ContactMessage::STATUS_NEW ? null : $request->user()->id,
            'handled_at' => $data['status'] === ContactMessage::STATUS_NEW ? null : now(),
        ]);

        return back()->with('status', 'Message updated.');
    }

    public function destroy(ContactMessage $message): RedirectResponse
    {
        $message->delete();

        return back()->with('status', 'Message deleted.');
    }
}
