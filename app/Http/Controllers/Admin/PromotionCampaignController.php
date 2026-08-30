<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PromotionEmail;
use App\Models\PromotionCampaign;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class PromotionCampaignController extends Controller
{
    public function index(): View
    {
        return view('admin.promotions.index', ['campaigns' => PromotionCampaign::latest()->paginate(25)]);
    }

    public function create(): View
    {
        return view('admin.promotions.form');
    }

    /**
     * Requirement 6: compose and send a promo blast. Recipients are
     * resolved from users.newsletter_opt_in (and role, for the
     * "customers" audience) at send time, queued individually so one bad
     * address doesn't block the batch, and the campaign row keeps a
     * permanent record of who got what and when.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'audience' => ['required', 'in:all,customers,newsletter_subscribers'],
        ]);

        $recipients = User::query()
            ->where('newsletter_opt_in', true)
            ->when($data['audience'] === 'customers', fn ($q) => $q->where('role', 'customer'))
            ->get();

        $campaign = PromotionCampaign::create([
            ...$data,
            'created_by' => $request->user()->id,
            'recipients_count' => $recipients->count(),
            'sent_at' => now(),
        ]);

        foreach ($recipients as $recipient) {
            Mail::to($recipient->email)->queue(new PromotionEmail($campaign));
        }

        return redirect()->route('admin.promotions.index')
            ->with('status', "Campaign sent to {$recipients->count()} subscriber(s).");
    }

    public function show(PromotionCampaign $promotion): View
    {
        return view('admin.promotions.show', ['campaign' => $promotion]);
    }
}
