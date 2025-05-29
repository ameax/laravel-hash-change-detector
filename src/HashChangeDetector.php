<?php

namespace ameax\HashChangeDetector;

use ameax\HashChangeDetector\Models\Publisher;

class HashChangeDetector
{
    /**
     * Register a publisher for a model.
     *
     * @param string $name
     * @param string $modelClass
     * @param string $publisherClass
     * @param bool $active
     * @return Publisher
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
     * @param string $modelClass
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPublishersForModel(string $modelClass)
    {
        return Publisher::forModel($modelClass)->get();
    }

    /**
     * Activate a publisher.
     *
     * @param int|Publisher $publisher
     * @return bool
     */
    public function activatePublisher($publisher): bool
    {
        if (!$publisher instanceof Publisher) {
            $publisher = Publisher::find($publisher);
        }

        if (!$publisher) {
            return false;
        }

        return $publisher->update(['status' => 'active']);
    }

    /**
     * Deactivate a publisher.
     *
     * @param int|Publisher $publisher
     * @return bool
     */
    public function deactivatePublisher($publisher): bool
    {
        if (!$publisher instanceof Publisher) {
            $publisher = Publisher::find($publisher);
        }

        if (!$publisher) {
            return false;
        }

        return $publisher->update(['status' => 'inactive']);
    }
}
