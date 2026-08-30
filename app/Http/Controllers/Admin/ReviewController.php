<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $reviews = Review::query()
            ->with(['user', 'reviewable', 'images'])
            ->when($request->get('filter', 'pending') === 'pending', fn ($q) => $q->where('is_approved', false))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.reviews.index', ['reviews' => $reviews, 'filter' => $request->get('filter', 'pending')]);
    }

    public function approve(Review $review): RedirectResponse
    {
        $review->update(['is_approved' => true]);

        return back()->with('status', 'Review approved.');
    }

    public function destroy(Review $review): RedirectResponse
    {
        $review->delete();

        return back()->with('status', 'Review removed.');
    }
}
