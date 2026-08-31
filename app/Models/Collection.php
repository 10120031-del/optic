<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

/**
 * A curated drop: a name, a bit of copy, a cover, and any mix of frames
 * and contact lenses.
 *
 * The lifecycle is deliberately two-step. The owner creates the collection
 * and attaches products at leisure — nothing is public and nobody is
 * notified. Announcing it is a separate, explicit action (see
 * Admin\CollectionController::announce) that stamps announced_at, puts the
 * collection on the storefront, and tells every customer. That split is what
 * stops a half-built collection from mailing the whole customer list.
 */
class Collection extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'cover_image',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'announced_at' => 'datetime',
        ];
    }

    /**
     * Slug is derived, never asked for in the form — the owner types a
     * name. Kept stable once set so an announced URL that customers may
     * already have followed does not rot when the name is tweaked.
     */
    protected static function booted(): void
    {
        static::creating(function (self $collection) {
            $collection->slug ??= static::uniqueSlug($collection->name);
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'collection';
        $slug = $base;

        for ($i = 2; static::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function frames(): MorphToMany
    {
        return $this->morphedByMany(Frame::class, 'item', 'collection_items')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function contactLenses(): MorphToMany
    {
        return $this->morphedByMany(ContactLens::class, 'item', 'collection_items')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isAnnounced(): bool
    {
        return $this->announced_at !== null;
    }

    /** Total products across both catalogues. */
    public function itemCount(): int
    {
        return $this->frames()->count() + $this->contactLenses()->count();
    }

    /** What the storefront is allowed to show: announced and still active. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNotNull('announced_at');
    }
}
