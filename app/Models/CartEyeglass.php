<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CartEyeglass extends Model
{
    protected $table = 'cart_eyeglasses';

    protected $fillable = [
        'cart_id',
        'frame_id',
        'lens_id',
        'prescription_id',
        'quantity',
        'left_sphere',
        'left_cylinder',
        'left_axis',
        'left_add',
        'right_sphere',
        'right_cylinder',
        'right_axis',
        'right_add',
        'pd',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'left_sphere' => 'decimal:2',
            'left_cylinder' => 'decimal:2',
            'left_add' => 'decimal:2',
            'right_sphere' => 'decimal:2',
            'right_cylinder' => 'decimal:2',
            'right_add' => 'decimal:2',
            'pd' => 'decimal:1',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function frame(): BelongsTo
    {
        return $this->belongsTo(Frame::class);
    }

    public function lens(): BelongsTo
    {
        return $this->belongsTo(Lens::class);
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(LensFeature::class, 'cart_eyeglass_features')
            ->withTimestamps();
    }
}
