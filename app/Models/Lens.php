<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lens extends Model
{
    protected $fillable = [
        'name',
        'material',
        'type',
        'refractive_index',
        'price',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'refractive_index' => 'decimal:2',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(LensFeature::class, 'lens_lens_feature')
            ->withTimestamps();
    }

    public function cartEyeglasses(): HasMany
    {
        return $this->hasMany(CartEyeglass::class);
    }
}
