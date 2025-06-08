<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Jobs;

use ameax\HashChangeDetector\Models\Publish;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeletePublishJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Publish $publish
    ) {
        $this->queue = config('laravel-hash-change-detector.queues.publish', 'default');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Mark as dispatched
        $this->publish->markAsDispatched();

        try {
            // Get the publisher instance
            $publisher = $this->publish->publisher;
            $deletePublisher = $publisher->getDeletePublisherInstance();

            if (! $deletePublisher) {
                throw new Exception("Publisher {$publisher->name} does not implement DeletePublisher interface");
            }

            // Get deletion data from metadata
            $metadata = $this->publish->metadata;

            if (! isset($metadata['type']) || $metadata['type'] !== 'deletion') {
                throw new Exception('Invalid metadata for deletion publish');
            }

            $modelClass = $metadata['model_class'] ?? '';
            $modelId = $metadata['model_id'] ?? 0;
            $lastKnownData = $metadata['last_known_data'] ?? [];

            if (empty($modelClass) || empty($modelId)) {
                throw new Exception('Missing model class or ID in metadata');
            }

            // Check if we should publish
            if (! $deletePublisher->shouldPublishDeletion($modelClass, $modelId)) {
                $this->publish->markAsPublished();

                return;
            }

            // Publish the deletion
            $success = $deletePublisher->publishDeletion($modelClass, $modelId, $lastKnownData);

            if ($success) {
                $this->publish->markAsPublished();
            } else {
                throw new Exception('Delete publisher returned false');
            }
        } catch (Exception $e) {
            $this->handleFailure($e);
        }
    }

    /**
     * Handle job failure.
     */
    protected function handleFailure(Exception $e): void
    {
        $error = $e->getMessage() ?: 'Unknown error';

        // Check if we should retry
        $maxAttempts = 3; // Default from config retry intervals
        try {
            $publisher = $this->publish->publisher;
            $deletePublisher = $publisher->getDeletePublisherInstance();
            if ($deletePublisher) {
                $maxAttempts = $deletePublisher->getMaxAttempts();
            }
        } catch (\Exception $instanceError) {
            // If we can't create the publisher instance, use default max attempts
        }

        if ($this->publish->attempts < $maxAttempts) {
            $this->publish->markAsDeferred($error);

            // Schedule retry if it's the first attempt (30 seconds)
            if ($this->publish->attempts === 1 && $this->publish->next_try) {
                $delay = (int) $this->publish->next_try->diffInSeconds(now());
                DeletePublishJob::dispatch($this->publish)->delay($delay);
            }
            // Other retries will be handled by the scheduler
        } else {
            $this->publish->markAsFailed($error);
        }
    }
}
