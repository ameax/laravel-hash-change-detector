<?php

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
    public function getHashableRelations(): array;

    /**
     * Get the current hash record for this model.
     *
     * @return \ameax\HashChangeDetector\Models\Hash|null
     */
    public function getCurrentHash(): ?object;

    /**
     * Calculate the attribute hash for this model.
     *
     * @return string
     */
    public function calculateAttributeHash(): string;

    /**
     * Calculate the composite hash including related models.
     *
     * @return string
     */
    public function calculateCompositeHash(): string;
}