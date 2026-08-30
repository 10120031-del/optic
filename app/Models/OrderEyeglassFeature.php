<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderEyeglassFeature extends Model
{
    protected $fillable = [
        'order_eyeglass_id',
        'lens_feature_id',
        'feature_name',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
        ];
    }

    public function orderEyeglass(): BelongsTo
    {
        return $this->belongsTo(OrderEyeglass::class);
    }

    public function lensFeature(): BelongsTo
    {
        return $this->belongsTo(LensFeature::class);
    }
}
