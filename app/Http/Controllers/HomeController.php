<?php

namespace App\Http\Controllers;

use App\Models\ContactLens;
use App\Models\Frame;
use App\Models\Review;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Storefront landing page: hero, category entry points, featured
     * frames, and a cross-catalog wall of the highest-rated approved
     * reviews (used as marketing testimonials, not the per-product
     * review thread).
     */
    public function index(): View
    {
        $featuredFrames = Frame::query()
            ->where('is_active', true)
            ->with('primaryImage')
            ->withAvg('approvedReviews as reviews_avg_rating', 'rating')
            ->withCount('approvedReviews as reviews_count')
            ->latest()
            ->take(8)
            ->get();

        $testimonials = Review::query()
            ->where('is_approved', true)
            ->where('rating', '>=', 4)
            ->whereNotNull('body')
            ->with(['user', 'reviewable'])
            ->latest()
            ->take(6)
            ->get();

        return view('home', [
            'featuredFrames' => $featuredFrames,
            'testimonials' => $testimonials,
            'frameCount' => Frame::where('is_active', true)->count(),
            'contactLensCount' => ContactLens::where('is_active', true)->count(),
            'reviewCount' => Review::where('is_approved', true)->count(),
        ]);
    }
}
