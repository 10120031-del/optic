<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eyeglasses(): HasMany
    {
        return $this->hasMany(CartEyeglass::class);
    }

    public function contactLenses(): HasMany
    {
        return $this->hasMany(CartContactLens::class);
    }
}
