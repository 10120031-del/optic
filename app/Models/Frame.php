<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Frame extends Model
{
    protected $fillable = [
        'name',
        'brand',
        'sku',
        'manufactured_in',
        'lens_width',
        'lens_height',
        'bridge_width',
        'temple_length',
        'frame_width',
        'weight_grams',
        'size',
        'description',
        'material',
        'category',
        'type',
        'shape',
        'gender',
        'color',
        'color_hex',
        'price',
        'stock',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'lens_width' => 'decimal:2',
            'lens_height' => 'decimal:2',
            'bridge_width' => 'decimal:2',
            'temple_length' => 'decimal:2',
            'frame_width' => 'decimal:2',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function cartEyeglasses(): HasMany
    {
        return $this->hasMany(CartEyeglass::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(FrameImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(FrameImage::class)->where('is_primary', true);
    }

    /**
     * Face shapes this frame is recommended for. Used by the face-scan AI
     * recommender to filter the catalog once it has classified the
     * customer's uploaded photo.
     */
    public function faceShapes(): BelongsToMany
    {
        return $this->belongsToMany(FaceShape::class, 'frame_face_shape')
            ->withTimestamps();
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
