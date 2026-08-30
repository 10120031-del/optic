<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrescriptionController extends Controller
{
    public function index(Request $request): View
    {
        $prescriptions = Prescription::query()
            ->with('user')
            ->when($request->get('filter', 'unverified') === 'unverified', fn ($q) => $q->where('is_verified', false))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.prescriptions.index', ['prescriptions' => $prescriptions, 'filter' => $request->get('filter', 'unverified')]);
    }

    public function show(Prescription $prescription): View
    {
        return view('admin.prescriptions.show', ['prescription' => $prescription->load('user')]);
    }

    /**
     * Staff sign-off that an uploaded prescription matches the numbers the
     * customer entered — the paper-trail half of requirement 3/checkout's
     * prescription handling.
     */
    public function verify(Request $request, Prescription $prescription): RedirectResponse
    {
        $prescription->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Prescription verified.');
    }
}
