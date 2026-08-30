<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'subtotal',
        'shipping_cost',
        'tax',
        'total',
        'shipping_address_line',
        'shipping_city',
        'shipping_postal_code',
        'shipping_country',
        'carrier',
        'tracking_number',
        'paid_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'estimated_delivery_date',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'estimated_delivery_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eyeglasses(): HasMany
    {
        return $this->hasMany(OrderEyeglass::class);
    }

    public function contactLenses(): HasMany
    {
        return $this->hasMany(OrderContactLens::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }
}
