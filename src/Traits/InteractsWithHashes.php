<?php

namespace ameax\HashChangeDetector\Traits;

use ameax\HashChangeDetector\Models\Hash;
use ameax\HashChangeDetector\Events\HashChanged;
use ameax\HashChangeDetector\Events\RelatedModelUpdated;
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
            event(new RelatedModelUpdated($model, 'created'));
        });

        static::updated(function ($model) {
            $model->updateHash();
            event(new RelatedModelUpdated($model, 'updated'));
        });

        static::deleting(function ($model) {
            // Fire event before deletion so parent models can be found
            event(new RelatedModelUpdated($model, 'deleting'));
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
        // Reload hashable relations to ensure we have fresh data
        if (!empty($this->getHashableRelations())) {
            $this->load($this->getHashableRelations());
        }
        
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
        }
    }

    /**
     * Delete the hash for this model.
     */
    public function deleteHash(): void
    {
        $this->hash()->delete();
    }

    /**
     * Check if a related model belongs to this model.
     */
    public function hasRelatedModel(Model $model): bool
    {
        foreach ($this->getHashableRelations() as $relationName) {
            // Handle nested relations
            if (str_contains($relationName, '.')) {
                continue; // Skip nested for now
            }
            
            $relation = $this->$relationName();
            
            // Check if the model belongs to this relation
            if ($relation instanceof \Illuminate\Database\Eloquent\Relations\HasMany ||
                $relation instanceof \Illuminate\Database\Eloquent\Relations\HasOne) {
                if ($model->getAttribute($relation->getForeignKeyName()) == $this->getKey()) {
                    return true;
                }
            } elseif ($relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany) {
                if ($relation->getRelated()::class === get_class($model)) {
                    return $relation->where($relation->getRelatedKeyName(), $model->getKey())->exists();
                }
            }
        }
        
        return false;
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
            // Ensure related models have hash records with parent reference
            return $related->map(function($model) {
                $this->ensureRelatedHashHasParentReference($model);
                return $model->calculateAttributeHash();
            });
        }
        
        $this->ensureRelatedHashHasParentReference($related);
        return collect([$related->calculateAttributeHash()]);
    }

    /**
     * Ensure related model hash has parent reference.
     */
    protected function ensureRelatedHashHasParentReference($relatedModel): void
    {
        if (!$relatedModel || !method_exists($relatedModel, 'getCurrentHash')) {
            return;
        }
        
        $hash = $relatedModel->getCurrentHash();
        if ($hash && (!$hash->main_model_type || !$hash->main_model_id)) {
            $hash->update([
                'main_model_type' => get_class($this),
                'main_model_id' => $this->getKey(),
            ]);
        }
    }

    /**
     * Generate hash using configured algorithm.
     */
    protected function generateHash(string $content): string
    {
        $algorithm = config('hash-change-detector.hash_algorithm', 'md5');
        
        return match ($algorithm) {
            'sha256' => hash('sha256', $content),
            default => md5($content),
        };
    }
    
    /**
     * Get parent models that should be notified of changes.
     * Override this method in your model to specify parent relationships.
     */
    public function getParentModels(): Collection
    {
        return collect();
    }
}