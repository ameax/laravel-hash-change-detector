<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Publishers;

use ameax\HashChangeDetector\Contracts\DeletePublisher;
use Illuminate\Support\Facades\Log;

class LogDeletePublisher implements DeletePublisher
{
    /**
     * Publish the deletion of a model to the log.
     */
    public function publishDeletion(string $modelClass, int $modelId, array $lastKnownData): bool
    {
        Log::info('Model deleted', [
            'model_class' => $modelClass,
            'model_id' => $modelId,
            'attribute_hash' => $lastKnownData['attribute_hash'] ?? null,
            'composite_hash' => $lastKnownData['composite_hash'] ?? null,
            'deleted_at' => $lastKnownData['deleted_at'] ?? now()->toIso8601String(),
        ]);

        return true;
    }

    /**
     * Determine if the deletion should be published.
     */
    public function shouldPublishDeletion(string $modelClass, int $modelId): bool
    {
        return true;
    }

    /**
     * Get the maximum number of retry attempts for deletion publishing.
     */
    public function getMaxAttempts(): int
    {
        return 1; // No retries for log publisher
    }
}