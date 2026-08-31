<?php

namespace App\Http\Controllers;

use App\Models\Frame;
use App\Models\Lens;
use App\Models\ProductView;
use App\Services\Recommender;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FrameController extends Controller
{
    public function __construct(private readonly Recommender $recommender)
    {
    }

    /**
     * Browse/search eyeglasses: free-text search plus the filters called
     * for in requirement 1 (color, gender, size), extended with the
     * shape/category/material facets the catalog also carries.
     */
    public function index(Request $request): View
    {
        $frames = Frame::query()
            ->where('is_active', true)
            ->when($request->filled('q'), fn ($q) => $q->where(function ($q) use ($request) {
                // whereLike, not where(...,'like',...): MySQL's default
                // collation ignores case but Postgres' LIKE does not, and a
                // shopper typing "harbor" should find "Harbor Classic" on
                // either. whereLike compiles to ILIKE on Postgres.
                $term = '%'.$request->string('q')->toString().'%';
                $q->whereLike('name', $term)->orWhereLike('brand', $term);
            }))
            ->when($request->filled('gender'), fn ($q) => $q->where('gender', $request->string('gender')->toString()))
            ->when($request->filled('color'), fn ($q) => $q->where('color', $request->string('color')->toString()))
            ->when($request->filled('size'), fn ($q) => $q->where('size', $request->string('size')->toString()))
            ->when($request->filled('shape'), fn ($q) => $q->where('shape', $request->string('shape')->toString()))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')->toString()))
            ->when($request->filled('material'), fn ($q) => $q->where('material', $request->string('material')->toString()))
            ->when($request->filled('min_price'), fn ($q) => $q->where('price', '>=', $request->float('min_price')))
            ->when($request->filled('max_price'), fn ($q) => $q->where('price', '<=', $request->float('max_price')))
            ->with('primaryImage')
            ->withAvg('approvedReviews as reviews_avg_rating', 'rating')
            ->withCount('approvedReviews as reviews_count')
            ->orderBy($this->sortColumn($request), $this->sortDirection($request))
            ->paginate(24)
            ->withQueryString();

        return view('frames.index', [
            'frames' => $frames,
            'filters' => $request->only(['q', 'gender', 'color', 'size', 'shape', 'category', 'material', 'min_price', 'max_price', 'sort']),
            'genders' => ['men', 'women', 'unisex', 'kids'],
            'sizes' => ['narrow', 'medium', 'wide'],
            'shapes' => ['round', 'square', 'rectangle', 'oval', 'cat_eye', 'aviator', 'wayfarer', 'browline', 'geometric', 'hexagonal'],
            'categories' => ['eyeglasses', 'sunglasses', 'sports'],
        ]);
    }

    public function show(Request $request, Frame $frame): View
    {
        abort_unless($frame->is_active, 404);

        ProductView::create([
            'viewable_type' => Frame::class,
            'viewable_id' => $frame->id,
            'user_id' => $request->user()?->id,
            'session_id' => $request->user() ? null : $request->session()->getId(),
        ]);

        $frame->load([
            'images',
            'faceShapes',
            'approvedReviews' => fn ($q) => $q->with(['user', 'images'])->latest(),
        ]);

        // Logged above before recommending, so the co-view signal counts this
        // visit — two shoppers comparing the same two frames is exactly what
        // the "you may also like" rail is built out of.
        $recommendations = $this->recommender->forProductPage($frame);

        return view('frames.show', [
            'frame' => $frame,
            'lenses' => Lens::where('is_active', true)->with('features')->get(),
            'similarFrames' => $recommendations['similar'],
            'alsoBought' => $recommendations['alsoBought'],
        ]);
    }

    private function sortColumn(Request $request): string
    {
        return match ($request->string('sort')->toString()) {
            'price_asc', 'price_desc' => 'price',
            'newest' => 'created_at',
            default => 'name',
        };
    }

    private function sortDirection(Request $request): string
    {
        return $request->string('sort')->toString() === 'price_desc' ? 'desc' : 'asc';
    }
}
