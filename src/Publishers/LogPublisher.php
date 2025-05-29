<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Publishers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class LogPublisher extends BasePublisher
{
    /**
     * The log channel to use.
     */
    protected ?string $channel = null;

    /**
     * The log level to use.
     */
    protected string $level = 'info';

    /**
     * Publish the model data to the external system.
     *
     * @param  Model  $model  The model to publish
     * @param  array  $data  The prepared data to publish
     * @return bool True if successful, false otherwise
     */
    public function publish(Model $model, array $data): bool
    {
        $logger = $this->channel ? Log::channel($this->channel) : Log::getFacadeRoot();

        $logger->log($this->level, 'Model hash changed', [
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'data' => $data,
        ]);

        return true;
    }
}
