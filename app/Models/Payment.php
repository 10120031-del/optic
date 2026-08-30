<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /**
     * The only method the shop accepts. The payments table still carries the
     * wider enum (card, paypal, bank_transfer) from the original schema, so
     * adding an online gateway later needs no migration — but nothing in the
     * application writes those values today.
     */
    public const METHOD_CASH_ON_DELIVERY = 'cash_on_delivery';

    /** Recorded at checkout: the courier has not collected the cash yet. */
    public const STATUS_PENDING = 'pending';

    /** The owner confirmed the cash was handed over. */
    public const STATUS_COMPLETED = 'completed';

    /** The order was cancelled, so the cash was never collected. */
    public const STATUS_FAILED = 'failed';

    /** Money given back after a return. */
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'order_id',
        'method',
        'status',
        'transaction_id',
        'amount',
        'currency',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
