<?php

namespace App\Http\Controllers;

use App\Models\ContactLens;
use App\Models\ProductView;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactLensController extends Controller
{
    public function index(Request $request): View
    {
        $lenses = ContactLens::query()
            ->where('is_active', true)
            ->when($request->filled('q'), fn ($q) => $q->where(function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where('name', 'like', $term)->orWhere('brand', 'like', $term);
            }))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->when($request->filled('material'), fn ($q) => $q->where('material', $request->string('material')->toString()))
            ->when($request->filled('color'), fn ($q) => $q->where('color', $request->string('color')->toString()))
            ->withAvg('approvedReviews as reviews_avg_rating', 'rating')
            ->withCount('approvedReviews as reviews_count')
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        return view('contact-lenses.index', [
            'lenses' => $lenses,
            'filters' => $request->only(['q', 'type', 'material', 'color']),
            'types' => ['daily', 'weekly', 'biweekly', 'monthly', 'yearly'],
        ]);
    }

    public function show(Request $request, ContactLens $contactLens): View
    {
        abort_unless($contactLens->is_active, 404);

        ProductView::create([
            'viewable_type' => ContactLens::class,
            'viewable_id' => $contactLens->id,
            'user_id' => $request->user()?->id,
            'session_id' => $request->user() ? null : $request->session()->getId(),
        ]);

        $contactLens->load(['approvedReviews' => fn ($q) => $q->with(['user', 'images'])->latest()]);

        return view('contact-lenses.show', ['contactLens' => $contactLens]);
    }
}
