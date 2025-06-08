<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Listeners;

use ameax\HashChangeDetector\Events\HashableModelDeleted;
use ameax\HashChangeDetector\Jobs\DeletePublishJob;
use ameax\HashChangeDetector\Models\Publish;
use ameax\HashChangeDetector\Models\Publisher;

class HandleHashableModelDeleted
{
    /**
     * Handle the event.
     */
    public function handle(HashableModelDeleted $event): void
    {
        // Find publishers that implement DeletePublisher for this model type
        $publishers = Publisher::where('model_type', $event->modelClass)
            ->where('status', 'active')
            ->get()
            ->filter(fn ($p) => $p->isDeletePublisher());

        foreach ($publishers as $publisher) {
            // Create a publish record for the deletion
            $publish = Publish::create([
                'hash_id' => null, // Indicates deletion
                'publisher_id' => $publisher->id,
                'published_hash' => $event->hash->attribute_hash,
                'status' => 'pending',
                'metadata' => [
                    'type' => 'deletion',
                    'model_class' => $event->modelClass,
                    'model_id' => $event->modelId,
                    'last_known_data' => [
                        'attribute_hash' => $event->hash->attribute_hash,
                        'composite_hash' => $event->hash->composite_hash,
                        'deleted_at' => now()->toIso8601String(),
                    ],
                ],
            ]);

            // Dispatch the deletion publish job
            DeletePublishJob::dispatch($publish);
        }
    }
}
