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
 * @property string|null $main_model_type
 * @property int|null $main_model_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model $hashable
 * @property-read \Illuminate\Database\Eloquent\Model|null $mainModel
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \ameax\HashChangeDetector\Models\Publish> $publishes
 */
class Hash extends Model
{
    protected $fillable = [
        'hashable_type',
        'hashable_id',
        'attribute_hash',
        'composite_hash',
        'main_model_type',
        'main_model_id',
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

    public function mainModel(): MorphTo
    {
        return $this->morphTo('mainModel', 'main_model_type', 'main_model_id');
    }

    public function relatedHashes(): HasMany
    {
        return $this->hasMany(Hash::class, 'main_model_id', 'hashable_id')
            ->where('main_model_type', $this->hashable_type);
    }

    public function publishes(): HasMany
    {
        return $this->hasMany(Publish::class);
    }

    public function isMainModel(): bool
    {
        return is_null($this->main_model_type);
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
