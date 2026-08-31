<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One product's position in the sentence-transformer's vector space.
 *
 * Vectors are stored L2-normalized (unit length), which is why every
 * similarity in App\Services\Recommender is a plain dot product: for unit
 * vectors, cosine similarity *is* the dot product, so the division and the
 * two square roots disappear from the hot loop.
 */
class ProductEmbedding extends Model
{
    protected $fillable = [
        'embeddable_type',
        'embeddable_id',
        'model',
        'dimensions',
        'vector',
        'content_hash',
    ];

    public function embeddable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Pack a vector for storage: float32 little-endian, then base64.
     *
     * @param  array<int, float>  $vector
     */
    public static function encode(array $vector): string
    {
        return base64_encode(pack('g*', ...$vector));
    }

    /**
     * @return array<int, float>
     */
    public static function decode(string $stored): array
    {
        return array_values(unpack('g*', base64_decode($stored)));
    }

    /**
     * @return array<int, float>
     */
    public function toVector(): array
    {
        return self::decode($this->vector);
    }
}
