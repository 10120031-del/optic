<?php

namespace App\Models;

use App\Observers\ContactMessageObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(ContactMessageObserver::class)]
class ContactMessage extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_READ = 'read';

    public const STATUS_CLOSED = 'closed';

    /**
     * The subjects offered by the About page's contact form. The keys are
     * what is stored; the labels are what both the form and the staff
     * console print, so the two can never disagree.
     *
     * @var array<string, string>
     */
    public const TOPICS = [
        'general' => 'General enquiry',
        'order' => 'An order I placed',
        'prescription' => 'Prescriptions and lenses',
        'returns' => 'Returns or exchanges',
        'wholesale' => 'Wholesale and partnerships',
        'other' => 'Something else',
    ];

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'topic',
        'message',
        'status',
        'handled_by',
        'handled_at',
    ];

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** Anything nobody has picked up yet — what the console badge counts. */
    public function scopeUnhandled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_NEW);
    }

    public function topicLabel(): string
    {
        return self::TOPICS[$this->topic] ?? self::TOPICS['general'];
    }
}
