<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $hashable_type
 * @property int $hashable_id
 * @property string $attribute_hash
 * @property string|null $composite_hash
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model $hashable
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \ameax\HashChangeDetector\Models\Publish> $publishes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \ameax\HashChangeDetector\Models\HashDependent> $dependents
 */
class Hash extends Model
{
    protected $fillable = [
        'hashable_type',
        'hashable_id',
        'attribute_hash',
        'composite_hash',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('laravel-hash-change-detector.tables.hashes', 'hashes');
    }

    public function hashable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get dependent models that should be notified when this hash changes.
     */
    public function dependents(): HasMany
    {
        return $this->hasMany(HashDependent::class, 'hash_id');
    }

    public function publishes(): HasMany
    {
        return $this->hasMany(Publish::class);
    }

    /**
     * Check if this hash has any dependent models.
     */
    public function hasDependents(): bool
    {
        return $this->dependents()->exists();
    }

    public function hasChanged(string $newHash): bool
    {
        return $this->attribute_hash !== $newHash;
    }

    public function hasCompositeChanged(string $newCompositeHash): bool
    {
        return $this->composite_hash !== $newCompositeHash;
    }
}
