<?php

namespace App\Http\Controllers;

use App\Models\ContactLens;
use App\Models\Frame;
use App\Models\Review;
use App\Services\Recommender;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(private readonly Recommender $recommender)
    {
    }

    /**
     * Storefront landing page: hero, category entry points, a spotlight
     * on the newest frame, featured frame and contact-lens grids, the
     * split category panels, and a cross-catalog wall of the
     * highest-rated approved reviews (used as marketing testimonials,
     * not the per-product review thread).
     *
     * Anyone who has browsed or bought before also gets a personalized rail
     * above the fixed merchandising. It is empty on a first-ever visit and
     * the section hides itself, so the page still reads as designed.
     */
    public function index(Request $request): View
    {
        $featuredFrames = Frame::query()
            ->where('is_active', true)
            ->with('primaryImage')
            ->withAvg('approvedReviews as reviews_avg_rating', 'rating')
            ->withCount('approvedReviews as reviews_count')
            ->latest()
            ->take(8)
            ->get();

        $featuredLenses = ContactLens::query()
            ->where('is_active', true)
            ->withAvg('approvedReviews as reviews_avg_rating', 'rating')
            ->withCount('approvedReviews as reviews_count')
            ->latest()
            ->take(6)
            ->get();

        // Spotlight the newest frame that actually has a photo — the layout
        // is built around a large image, so a picture-less frame would leave
        // a hole. Falls back to the newest featured frame when the catalog
        // has no imagery yet.
        $spotlightFrame = $featuredFrames->firstWhere('primaryImage', '!=', null)
            ?? $featuredFrames->first();

        $testimonials = Review::query()
            ->where('is_approved', true)
            ->where('rating', '>=', 4)
            ->whereNotNull('body')
            ->with(['user', 'reviewable'])
            ->latest()
            ->take(6)
            ->get();

        // Guests are personalized on their session id, which is what
        // ProductView already records for them, so the rail works before
        // anyone signs in.
        $recommended = $this->recommender->forShopper(
            $request->user(),
            $request->user() ? null : $request->session()->getId(),
        );

        return view('home', [
            'recommended' => $recommended,
            'featuredFrames' => $featuredFrames,
            'featuredLenses' => $featuredLenses,
            'spotlightFrame' => $spotlightFrame,
            // Big photos for the split category panels, taken straight from
            // the catalog so the tiles stay in sync with what's in stock.
            'framePanelImage' => $featuredFrames->pluck('primaryImage')->filter()->first()?->path,
            'lensPanelImage' => $featuredLenses->pluck('image_path')->filter()->first(),
            'testimonials' => $testimonials,
            'frameCount' => Frame::where('is_active', true)->count(),
            'contactLensCount' => ContactLens::where('is_active', true)->count(),
            'reviewCount' => Review::where('is_approved', true)->count(),
        ]);
    }
}
