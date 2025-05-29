<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $hash_id
 * @property int $publisher_id
 * @property string $published_hash
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property string $status
 * @property int $attempts
 * @property string|null $last_error
 * @property \Illuminate\Support\Carbon|null $next_try
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \ameax\HashChangeDetector\Models\Hash $hash
 * @property-read \ameax\HashChangeDetector\Models\Publisher $publisher
 */
class Publish extends Model
{
    protected $fillable = [
        'hash_id',
        'publisher_id',
        'published_hash',
        'published_at',
        'status',
        'attempts',
        'last_error',
        'next_try',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'next_try' => 'datetime',
        'attempts' => 'integer',
        'status' => 'string',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('laravel-hash-change-detector.tables.publishes', 'publishes');
    }

    public function hash(): BelongsTo
    {
        return $this->belongsTo(Hash::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isDispatched(): bool
    {
        return $this->status === 'dispatched';
    }

    public function isDeferred(): bool
    {
        return $this->status === 'deferred';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function shouldRetry(): bool
    {
        return $this->isDeferred() &&
               $this->next_try &&
               $this->next_try->isPast();
    }

    public function markAsDispatched(): void
    {
        $this->update(['status' => 'dispatched']);
    }

    public function markAsPublished(): void
    {
        $this->update([
            'status' => 'published',
            'published_at' => now(),
            'last_error' => null,
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'last_error' => $error,
        ]);
    }

    public function markAsDeferred(string $error): void
    {
        $this->attempts++;

        $retryIntervals = config('laravel-hash-change-detector.retry_intervals', [
            1 => 30,
            2 => 300,
            3 => 21600,
        ]);

        if ($this->attempts > count($retryIntervals)) {
            $this->markAsFailed($error);

            return;
        }

        $this->update([
            'status' => 'deferred',
            'attempts' => $this->attempts,
            'last_error' => $error,
            'next_try' => now()->addSeconds($retryIntervals[$this->attempts]),
        ]);
    }

    public function scopePendingOrDeferred(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where(function ($q) {
            $q->where('status', 'pending')
                ->orWhere(function ($q) {
                    $q->where('status', 'deferred')
                        ->where('next_try', '<=', now());
                });
        });
    }
}
