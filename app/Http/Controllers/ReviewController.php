<?php

namespace App\Http\Controllers;

use App\Models\ContactLens;
use App\Models\Frame;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    private const TYPE_MAP = [
        'frame' => Frame::class,
        'contact_lens' => ContactLens::class,
    ];

    /**
     * One review per customer per product (enforced at the DB level too —
     * see the reviews_one_per_user_per_product unique index). Verified
     * purchase is derived server-side from the customer's own order
     * history, never trusted from the request.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reviewable_type' => ['required', 'in:frame,contact_lens'],
            'reviewable_id' => ['required', 'integer'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:4000'],
            'images' => ['sometimes', 'array', 'max:6'],
            'images.*' => ['image', 'max:8192'],
        ]);

        $reviewableClass = self::TYPE_MAP[$data['reviewable_type']];
        $product = $reviewableClass::findOrFail($data['reviewable_id']);

        $alreadyReviewed = Review::where('user_id', $request->user()->id)
            ->where('reviewable_type', $reviewableClass)
            ->where('reviewable_id', $product->id)
            ->exists();

        if ($alreadyReviewed) {
            throw ValidationException::withMessages(['rating' => 'You\'ve already reviewed this product.']);
        }

        $isVerifiedPurchase = $this->hasPurchased($request->user()->id, $reviewableClass, $product->id);

        $review = Review::create([
            'reviewable_type' => $reviewableClass,
            'reviewable_id' => $product->id,
            'user_id' => $request->user()->id,
            'order_id' => null,
            'rating' => $data['rating'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'is_verified_purchase' => $isVerifiedPurchase,
            'is_approved' => false,
        ]);

        foreach ($request->file('images', []) as $index => $image) {
            $review->images()->create([
                'path' => $image->store('reviews', 'public'),
                'sort_order' => $index,
            ]);
        }

        return back()->with('status', 'Thanks for the review — it\'ll show up once our team approves it.');
    }

    public function destroy(Request $request, Review $review): RedirectResponse
    {
        abort_unless($review->user_id === $request->user()->id, 403);
        $review->delete();

        return back()->with('status', 'Review removed.');
    }

    private function hasPurchased(int $userId, string $reviewableClass, int $productId): bool
    {
        if ($reviewableClass === Frame::class) {
            return Order::where('user_id', $userId)
                ->whereHas('eyeglasses', fn ($q) => $q->where('frame_id', $productId))
                ->exists();
        }

        return Order::where('user_id', $userId)
            ->whereHas('contactLenses', fn ($q) => $q->where('contact_lens_id', $productId))
            ->exists();
    }
}
