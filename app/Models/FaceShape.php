<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FaceShape extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function frames(): BelongsToMany
    {
        return $this->belongsToMany(Frame::class, 'frame_face_shape')
            ->withTimestamps();
    }
}
