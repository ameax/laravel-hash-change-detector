<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Contracts;

interface Hashable
{
    /**
     * Get the attributes that should be included in the hash.
     *
     * @return array<string>
     */
    public function getHashableAttributes(): array;

    /**
     * Get the relations that should be included in the composite hash.
     * Can include nested relations using dot notation (e.g., 'posts.comments').
     *
     * @return array<string>
     */
    public function getHashCompositeDependencies(): array;

    /**
     * Get the current hash record for this model.
     *
     * @return \ameax\HashChangeDetector\Models\Hash|null
     */
    public function getCurrentHash(): ?object;

    /**
     * Calculate the attribute hash for this model.
     */
    public function calculateAttributeHash(): string;

    /**
     * Calculate the composite hash including related models.
     */
    public function calculateCompositeHash(): string;

    /**
     * Get the relations that should be notified when this model's hash changes.
     * Return an array of relation names that point to dependent models.
     *
     * @return array<string>
     */
    public function getHashRelationsToNotifyOnChange(): array;
}
