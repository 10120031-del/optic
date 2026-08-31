<?php

namespace App\Models;

use App\Observers\PrescriptionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(PrescriptionObserver::class)]
class Prescription extends Model
{
    protected $fillable = [
        'user_id',
        'file_path',
        'doctor_name',
        'clinic_name',
        'issued_at',
        'expires_at',
        'left_sphere',
        'left_cylinder',
        'left_axis',
        'left_add',
        'right_sphere',
        'right_cylinder',
        'right_axis',
        'right_add',
        'pd',
        'is_verified',
        'verified_at',
        'verified_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
            'left_sphere' => 'decimal:2',
            'left_cylinder' => 'decimal:2',
            'left_add' => 'decimal:2',
            'right_sphere' => 'decimal:2',
            'right_cylinder' => 'decimal:2',
            'right_add' => 'decimal:2',
            'pd' => 'decimal:1',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function cartEyeglasses(): HasMany
    {
        return $this->hasMany(CartEyeglass::class);
    }

    public function orderEyeglasses(): HasMany
    {
        return $this->hasMany(OrderEyeglass::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
