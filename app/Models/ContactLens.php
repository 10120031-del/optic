<?php

namespace App\Models;

use App\Observers\StockObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[ObservedBy(StockObserver::class)]
class ContactLens extends Model
{
    /** Boxes move faster than frames, so the low-stock line sits higher. */
    public const LOW_STOCK_THRESHOLD = 10;

    protected $table = 'contact_lenses';

    protected $fillable = [
        'name',
        'brand',
        'sku',
        'type',
        'material',
        'color',
        'diameter',
        'base_curve',
        'pack_size',
        'expiry_months',
        'price',
        'description',
        'image_path',
        'stock',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'diameter' => 'decimal:2',
            'base_curve' => 'decimal:2',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartContactLens::class);
    }

    public function views(): MorphMany
    {
        return $this->morphMany(ProductView::class, 'viewable');
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function approvedReviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable')->where('is_approved', true);
    }
}
