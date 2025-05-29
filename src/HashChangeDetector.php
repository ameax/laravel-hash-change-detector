<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector;

use ameax\HashChangeDetector\Models\Publisher;

class HashChangeDetector
{
    /**
     * Register a publisher for a model.
     */
    public function registerPublisher(string $name, string $modelClass, string $publisherClass, bool $active = true): Publisher
    {
        return Publisher::create([
            'name' => $name,
            'model_type' => $modelClass,
            'publisher_class' => $publisherClass,
            'status' => $active ? 'active' : 'inactive',
        ]);
    }

    /**
     * Get all publishers for a model.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPublishersForModel(string $modelClass): \Illuminate\Database\Eloquent\Collection
    {
        return Publisher::forModel($modelClass)->get();
    }

    /**
     * Activate a publisher.
     *
     * @param  int|Publisher  $publisher
     */
    public function activatePublisher(int|Publisher $publisher): bool
    {
        if (! $publisher instanceof Publisher) {
            $publisher = Publisher::find($publisher);
        }

        if (! $publisher) {
            return false;
        }

        return $publisher->update(['status' => 'active']);
    }

    /**
     * Deactivate a publisher.
     *
     * @param  int|Publisher  $publisher
     */
    public function deactivatePublisher(int|Publisher $publisher): bool
    {
        if (! $publisher instanceof Publisher) {
            $publisher = Publisher::find($publisher);
        }

        if (! $publisher) {
            return false;
        }

        return $publisher->update(['status' => 'inactive']);
    }
}
