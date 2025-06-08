<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Contracts;

interface DeletePublisher
{
    /**
     * Publish the deletion of a model to the external system.
     *
     * @param  string  $modelClass  The class name of the deleted model
     * @param  int  $modelId  The ID of the deleted model
     * @param  array  $lastKnownData  The last known data from the hash record
     * @return bool True if successful, false otherwise
     */
    public function publishDeletion(string $modelClass, int $modelId, array $lastKnownData): bool;

    /**
     * Determine if the deletion should be published.
     *
     * @param  string  $modelClass  The class name of the deleted model
     * @param  int  $modelId  The ID of the deleted model
     * @return bool True if should publish, false otherwise
     */
    public function shouldPublishDeletion(string $modelClass, int $modelId): bool;

    /**
     * Get the maximum number of retry attempts for deletion publishing.
     */
    public function getMaxAttempts(): int;
}
