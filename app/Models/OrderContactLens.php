<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OrderContactLens extends Model
{
    protected $table = 'order_contact_lenses';

    protected $fillable = [
        'order_id',
        'contact_lens_id',
        'quantity',
        'product_name',
        'brand',
        'unit_price',
        'line_total',
        'left_power',
        'right_power',
        'left_cylinder',
        'right_cylinder',
        'left_axis',
        'right_axis',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'left_power' => 'decimal:2',
            'right_power' => 'decimal:2',
            'left_cylinder' => 'decimal:2',
            'right_cylinder' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function contactLens(): BelongsTo
    {
        return $this->belongsTo(ContactLens::class);
    }

    public function returnItems(): MorphMany
    {
        return $this->morphMany(OrderReturnItem::class, 'returnable');
    }
}
