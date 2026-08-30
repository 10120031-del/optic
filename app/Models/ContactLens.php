<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ContactLens extends Model
{
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
