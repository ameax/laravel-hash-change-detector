<?php

namespace ameax\HashChangeDetector\Jobs;

use ameax\HashChangeDetector\Models\Publish;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishModelJob implements ShouldQueue
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
            $publisherInstance = $publisher->getPublisherInstance();
            
            // Get the model through the hash relationship
            $model = $this->publish->hash->hashable;
            
            if (!$model) {
                throw new Exception('Model not found for hash ID: ' . $this->publish->hash_id);
            }
            
            // Check if we should publish
            if (!$publisherInstance->shouldPublish($model)) {
                $this->publish->markAsPublished();
                return;
            }
            
            // Prepare the data
            $data = $publisherInstance->getData($model);
            
            // Publish the data
            $success = $publisherInstance->publish($model, $data);
            
            if ($success) {
                $this->publish->markAsPublished();
            } else {
                throw new Exception('Publisher returned false');
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
            if ($this->publish->publisher) {
                $publisherInstance = $this->publish->publisher->getPublisherInstance();
                $maxAttempts = $publisherInstance->getMaxAttempts();
            }
        } catch (\Exception $instanceError) {
            // If we can't create the publisher instance, use default max attempts
        }
        
        if ($this->publish->attempts < $maxAttempts) {
            $this->publish->markAsDeferred($error);
            
            // Schedule retry if it's the first attempt (30 seconds)
            if ($this->publish->attempts === 1 && $this->publish->next_try) {
                $delay = $this->publish->next_try->diffInSeconds(now());
                PublishModelJob::dispatch($this->publish)->delay($delay);
            }
            // Other retries will be handled by the scheduler
        } else {
            $this->publish->markAsFailed($error);
        }
    }
}