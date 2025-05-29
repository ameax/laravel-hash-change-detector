<?php

namespace ameax\HashChangeDetector\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForModel($query, string $modelType)
    {
        return $query->where('model_type', $modelType);
    }

    public function getPublisherInstance(): object
    {
        return app($this->publisher_class);
    }
}