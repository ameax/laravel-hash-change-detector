<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Models;

use ameax\HashChangeDetector\Database\Factories\PublisherFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $model_type
 * @property string $publisher_class
 * @property string $status
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Publisher extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'model_type',
        'publisher_class',
        'status',
        'config',
    ];

    protected $casts = [
        'status' => 'string',
        'config' => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('laravel-hash-change-detector.tables.publishers', 'publishers');
    }

    public function publishes(): HasMany
    {
        return $this->hasMany(Publish::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeForModel(\Illuminate\Database\Eloquent\Builder $query, string $modelType): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('model_type', $modelType);
    }

    public function getPublisherInstance(): object
    {
        return app($this->publisher_class);
    }

    /**
     * Check if this publisher implements the DeletePublisher interface.
     */
    public function isDeletePublisher(): bool
    {
        return is_subclass_of($this->publisher_class, \ameax\HashChangeDetector\Contracts\DeletePublisher::class);
    }

    /**
     * Get an instance of the publisher as a DeletePublisher if it implements the interface.
     *
     * @throws \Exception
     */
    public function getDeletePublisherInstance(): ?\ameax\HashChangeDetector\Contracts\DeletePublisher
    {
        if (! $this->isDeletePublisher()) {
            return null;
        }

        return app($this->publisher_class);
    }

    protected static function newFactory(): PublisherFactory
    {
        return PublisherFactory::new();
    }
}
