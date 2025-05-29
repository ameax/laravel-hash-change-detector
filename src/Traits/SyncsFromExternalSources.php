<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Traits;

/**
 * Helper trait for models that receive updates from external APIs.
 * Provides convenient methods for syncing without triggering publishers.
 */
trait SyncsFromExternalSources
{
    /**
     * Update model from external data without triggering publishers.
     *
     * @param  array  $data  Data to update
     * @param  string|array|null  $sourcePublisher  Publisher(s) that provided this data
     */
    public function syncFromExternal(array $data, array|string|null $sourcePublisher = null): bool
    {
        // Temporarily disable model events to prevent double hash updates
        $dispatcher = static::getEventDispatcher();
        static::unsetEventDispatcher();

        // Update the model
        $result = $this->update($data);

        // Re-enable events
        static::setEventDispatcher($dispatcher);

        // Update hash without triggering publishers
        if ($result && method_exists($this, 'updateHashWithoutPublishing')) {
            $this->updateHashWithoutPublishing($sourcePublisher);
        }

        return $result;
    }

    /**
     * Create or update model from external data without triggering publishers.
     *
     * @param  array  $attributes  Attributes to find the model
     * @param  array  $values  Values to update
     * @param  string|array|null  $sourcePublisher  Publisher(s) that provided this data
     */
    public static function syncOrCreateFromExternal(array $attributes, array $values = [], array|string|null $sourcePublisher = null): static
    {
        $model = static::firstOrNew($attributes);
        $model->fill($values);

        // Track if this is a new model
        $isNew = ! $model->exists;

        // Temporarily disable model events
        $dispatcher = static::getEventDispatcher();
        static::unsetEventDispatcher();

        $model->save();

        // Re-enable events
        static::setEventDispatcher($dispatcher);

        // Update hash without triggering publishers
        if (method_exists($model, 'updateHashWithoutPublishing')) {
            $model->updateHashWithoutPublishing($sourcePublisher);
        }

        return $model;
    }

    /**
     * Bulk sync models from external data.
     *
     * @param  array  $items  Array of items to sync
     * @param  string  $keyAttribute  Attribute to use as unique key
     * @param  string|array|null  $sourcePublisher  Publisher(s) that provided this data
     */
    public static function bulkSyncFromExternal(array $items, string $keyAttribute = 'id', array|string|null $sourcePublisher = null): \Illuminate\Support\Collection
    {
        $synced = collect();

        foreach ($items as $item) {
            if (! isset($item[$keyAttribute])) {
                continue;
            }

            $model = static::syncOrCreateFromExternal(
                [$keyAttribute => $item[$keyAttribute]],
                $item,
                $sourcePublisher
            );

            $synced->push($model);
        }

        return $synced;
    }
}
