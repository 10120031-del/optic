<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use Illuminate\View\View;

/**
 * The storefront side of collections. Only announced, active ones exist
 * here — an unannounced collection is the owner's private draft, so it 404s
 * rather than 403s: a customer who guesses a slug should not learn that a
 * collection by that name is being prepared.
 */
class CollectionController extends Controller
{
    public function index(): View
    {
        return view('collections.index', [
            'collections' => Collection::published()
                ->withCount(['frames', 'contactLenses'])
                ->orderByDesc('announced_at')
                ->get(),
        ]);
    }

    public function show(Collection $collection): View
    {
        abort_unless($collection->is_active && $collection->isAnnounced(), 404);

        // Review aggregates so the shared product cards render their stars
        // the same way the catalogue pages do.
        $collection->load([
            'frames' => fn ($q) => $q->where('is_active', true)
                ->with('primaryImage')
                ->withAvg('approvedReviews as reviews_avg_rating', 'rating')
                ->withCount('approvedReviews as reviews_count'),
            'contactLenses' => fn ($q) => $q->where('is_active', true)
                ->withAvg('approvedReviews as reviews_avg_rating', 'rating')
                ->withCount('approvedReviews as reviews_count'),
        ]);

        return view('collections.show', ['collection' => $collection]);
    }
}
