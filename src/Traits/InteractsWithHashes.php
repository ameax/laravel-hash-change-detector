<?php

namespace ameax\HashChangeDetector\Traits;

use ameax\HashChangeDetector\Models\Hash;
use ameax\HashChangeDetector\Events\HashChanged;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Collection;

trait InteractsWithHashes
{
    /**
     * Boot the trait.
     */
    public static function bootInteractsWithHashes(): void
    {
        static::created(function ($model) {
            $model->updateHash();
        });

        static::updated(function ($model) {
            $model->updateHash();
        });

        static::deleted(function ($model) {
            $model->deleteHash();
        });
    }

    /**
     * Get the hash record for this model.
     */
    public function hash(): MorphOne
    {
        return $this->morphOne(Hash::class, 'hashable');
    }

    /**
     * Get the current hash record for this model.
     */
    public function getCurrentHash(): ?Hash
    {
        return $this->hash()->first();
    }

    /**
     * Calculate the attribute hash for this model.
     */
    public function calculateAttributeHash(): string
    {
        $attributes = $this->getHashableAttributesData();
        
        // Convert attributes to string values, using empty string for null
        $values = array_map(function ($value) {
            if (is_null($value)) {
                return '';
            }
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }
            return (string) $value;
        }, $attributes);
        
        // Concatenate with pipe separator
        $content = implode('|', $values);
        
        return $this->generateHash($content);
    }

    /**
     * Calculate the composite hash including related models.
     */
    public function calculateCompositeHash(): string
    {
        $hashes = collect([$this->calculateAttributeHash()]);
        
        foreach ($this->getHashableRelations() as $relation) {
            $hashes = $hashes->merge($this->getRelationHashes($relation));
        }
        
        // Sort hashes to ensure consistent ordering
        $sortedHashes = $hashes->sort()->values()->implode('|');
        
        return $this->generateHash($sortedHashes);
    }

    /**
     * Update the hash for this model.
     */
    public function updateHash(): void
    {
        $attributeHash = $this->calculateAttributeHash();
        $compositeHash = $this->calculateCompositeHash();
        
        $currentHash = $this->getCurrentHash();
        
        if (!$currentHash || 
            $currentHash->attribute_hash !== $attributeHash || 
            $currentHash->composite_hash !== $compositeHash) {
            
            $this->hash()->updateOrCreate([], [
                'attribute_hash' => $attributeHash,
                'composite_hash' => $compositeHash,
            ]);
            
            // Fire event for hash change
            event(new HashChanged($this, $attributeHash, $compositeHash));
            
            // Update parent model's composite hash if this is a related model
            $this->updateParentHashes();
        }
    }

    /**
     * Delete the hash for this model.
     */
    public function deleteHash(): void
    {
        $this->hash()->delete();
        $this->updateParentHashes();
    }

    /**
     * Get the data for hashable attributes.
     */
    protected function getHashableAttributesData(): array
    {
        $attributes = [];
        
        foreach ($this->getHashableAttributes() as $attribute) {
            $attributes[$attribute] = $this->getAttribute($attribute);
        }
        
        // Sort by key to ensure consistent ordering
        ksort($attributes);
        
        return $attributes;
    }

    /**
     * Get hashes for a specific relation.
     */
    protected function getRelationHashes(string $relationName): Collection
    {
        // Handle nested relations (e.g., 'posts.comments')
        if (str_contains($relationName, '.')) {
            [$relation, $nested] = explode('.', $relationName, 2);
            $models = $this->$relation;
            
            if (!$models) {
                return collect();
            }
            
            if ($models instanceof Collection) {
                return $models->flatMap(fn($model) => $model->getRelationHashes($nested));
            }
            
            return $models->getRelationHashes($nested);
        }
        
        $related = $this->$relationName;
        
        if (!$related) {
            return collect();
        }
        
        if ($related instanceof Collection) {
            return $related->map(fn($model) => $model->calculateAttributeHash());
        }
        
        return collect([$related->calculateAttributeHash()]);
    }

    /**
     * Update parent model hashes when this model changes.
     */
    protected function updateParentHashes(): void
    {
        $parentHashes = Hash::where('main_model_type', get_class($this))
            ->where('main_model_id', $this->getKey())
            ->get();
        
        foreach ($parentHashes as $parentHash) {
            if ($parentHash->hashable) {
                $parentHash->hashable->updateHash();
            }
        }
    }

    /**
     * Generate hash using configured algorithm.
     */
    protected function generateHash(string $content): string
    {
        $algorithm = config('laravel-hash-change-detector.hash_algorithm', 'md5');
        
        return match ($algorithm) {
            'sha256' => hash('sha256', $content),
            default => md5($content),
        };
    }
}