<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OrderReturnItem extends Model
{
    protected $fillable = [
        'order_return_id',
        'returnable_type',
        'returnable_id',
        'quantity',
        'condition_notes',
    ];

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class);
    }

    public function returnable(): MorphTo
    {
        return $this->morphTo();
    }
}
