<?php

namespace App\Models;

use App\Observers\OrderReturnObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(OrderReturnObserver::class)]
class OrderReturn extends Model
{
    protected $table = 'order_returns';

    protected $fillable = [
        'order_id',
        'requested_by',
        'type',
        'reason',
        'reason_details',
        'status',
        'refund_amount',
        'exchange_order_id',
        'staff_notes',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'refund_amount' => 'decimal:2',
            'resolved_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function exchangeOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'exchange_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class);
    }
}
