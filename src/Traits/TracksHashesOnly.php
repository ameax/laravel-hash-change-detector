<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Traits;

use ameax\HashChangeDetector\Models\Hash;

/**
 * Trait for read-only models that only need hash tracking via direct database detection.
 * Does not hook into Eloquent events since these models are not modified through Laravel.
 */
trait TracksHashesOnly
{
    use InteractsWithHashes {
        InteractsWithHashes::bootInteractsWithHashes as private bootInteractsWithHashesOriginal;
    }

    /**
     * Override boot method to skip Eloquent event hooks.
     */
    public static function bootTracksHashesOnly(): void
    {
        // Intentionally empty - we don't want Eloquent event hooks for read-only models
    }

    /**
     * Initialize hash for read-only model if it doesn't exist.
     * Call this method manually or via command/job.
     */
    public function initializeHash(): void
    {
        if (! $this->getCurrentHash()) {
            $this->updateHash();
        }
    }

    /**
     * Check if this model's hash needs updating.
     * Useful for selective updates in custom jobs.
     */
    public function needsHashUpdate(): bool
    {
        $currentHash = $this->getCurrentHash();

        if (! $currentHash) {
            return true;
        }

        $calculatedAttributeHash = $this->calculateAttributeHash();
        $calculatedCompositeHash = $this->calculateCompositeHash();

        return $currentHash->attribute_hash !== $calculatedAttributeHash ||
               $currentHash->composite_hash !== $calculatedCompositeHash;
    }
}
