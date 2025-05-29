<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Publishers;

use ameax\HashChangeDetector\Contracts\Publisher;
use Illuminate\Database\Eloquent\Model;

abstract class BasePublisher implements Publisher
{
    /**
     * Determine if the model should be published.
     * By default, all models are published.
     *
     * @param  Model  $model  The model to check
     * @return bool True if should publish, false otherwise
     */
    public function shouldPublish(Model $model): bool
    {
        return true;
    }

    /**
     * Get the maximum number of retry attempts for this publisher.
     * By default, uses the number of retry intervals configured.
     */
    public function getMaxAttempts(): int
    {
        return count(config('laravel-hash-change-detector.retry_intervals', [1 => 30, 2 => 300, 3 => 21600]));
    }

    /**
     * Prepare the data for publishing.
     * By default, returns the model and its loaded relations as array.
     *
     * @param  Model  $model  The model to prepare data for
     * @return array The prepared data
     */
    public function getData(Model $model): array
    {
        return [
            'model' => $model->toArray(),
            'meta' => [
                'timestamp' => now()->toIso8601String(),
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
            ],
        ];
    }
}
