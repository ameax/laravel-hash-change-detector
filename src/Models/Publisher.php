<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Models;

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
    protected $fillable = [
        'name',
        'model_type',
        'publisher_class',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
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
}
