<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartContactLens extends Model
{
    protected $table = 'cart_contact_lenses';

    protected $fillable = [
        'cart_id',
        'contact_lens_id',
        'quantity',
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
            'left_power' => 'decimal:2',
            'right_power' => 'decimal:2',
            'left_cylinder' => 'decimal:2',
            'right_cylinder' => 'decimal:2',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function contactLens(): BelongsTo
    {
        return $this->belongsTo(ContactLens::class);
    }
}
