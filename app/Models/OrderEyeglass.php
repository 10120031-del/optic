<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OrderEyeglass extends Model
{
    protected $table = 'order_eyeglasses';

    protected $fillable = [
        'order_id',
        'frame_id',
        'lens_id',
        'prescription_id',
        'quantity',
        'frame_name',
        'frame_brand',
        'lens_name',
        'frame_unit_price',
        'lens_unit_price',
        'features_unit_price',
        'line_total',
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
            'frame_unit_price' => 'decimal:2',
            'lens_unit_price' => 'decimal:2',
            'features_unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'left_sphere' => 'decimal:2',
            'left_cylinder' => 'decimal:2',
            'left_add' => 'decimal:2',
            'right_sphere' => 'decimal:2',
            'right_cylinder' => 'decimal:2',
            'right_add' => 'decimal:2',
            'pd' => 'decimal:1',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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

    public function features(): HasMany
    {
        return $this->hasMany(OrderEyeglassFeature::class);
    }

    public function returnItems(): MorphMany
    {
        return $this->morphMany(OrderReturnItem::class, 'returnable');
    }
}
