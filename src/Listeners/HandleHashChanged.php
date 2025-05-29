<?php

namespace ameax\HashChangeDetector\Listeners;

use ameax\HashChangeDetector\Events\HashChanged;
use ameax\HashChangeDetector\Jobs\PublishModelJob;
use ameax\HashChangeDetector\Models\Publisher;
use ameax\HashChangeDetector\Models\Publish;
use Illuminate\Contracts\Queue\ShouldQueue;

class HandleHashChanged implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(HashChanged $event): void
    {
        $model = $event->model;
        $modelType = get_class($model);
        
        // Get the hash record
        $hash = $model->getCurrentHash();
        
        if (!$hash) {
            return;
        }
        
        // Find all active publishers for this model type
        $publishers = Publisher::active()
            ->forModel($modelType)
            ->get();
        
        foreach ($publishers as $publisher) {
            // Check if we need to create a new publish record
            $publish = Publish::firstOrCreate([
                'hash_id' => $hash->id,
                'publisher_id' => $publisher->id,
            ], [
                'published_hash' => $event->compositeHash,
                'status' => 'pending',
                'attempts' => 0,
            ]);
            
            // If the hash has changed or it's a new record, dispatch the job
            if ($publish->wasRecentlyCreated || $publish->published_hash !== $event->compositeHash) {
                $publish->update([
                    'published_hash' => $event->compositeHash,
                    'status' => 'pending',
                    'attempts' => 0,
                    'last_error' => null,
                    'next_try' => null,
                ]);
                
                PublishModelJob::dispatch($publish);
            }
        }
    }

    /**
     * Get the name of the listener's queue.
     */
    public function viaQueue(): string
    {
        return config('laravel-hash-change-detector.queues.publish', 'default');
    }
}