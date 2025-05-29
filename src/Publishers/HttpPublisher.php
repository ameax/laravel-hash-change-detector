<?php

namespace ameax\HashChangeDetector\Publishers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

abstract class HttpPublisher extends BasePublisher
{
    /**
     * Get the endpoint URL for publishing.
     *
     * @param Model $model
     * @return string
     */
    abstract protected function getEndpoint(Model $model): string;

    /**
     * Get HTTP headers for the request.
     *
     * @return array
     */
    protected function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Get the HTTP method to use.
     *
     * @return string
     */
    protected function getMethod(): string
    {
        return 'POST';
    }

    /**
     * Get the HTTP timeout in seconds.
     *
     * @return int
     */
    protected function getTimeout(): int
    {
        return 30;
    }

    /**
     * Publish the model data to the external system.
     *
     * @param Model $model The model to publish
     * @param array $data The prepared data to publish
     * @return bool True if successful, false otherwise
     */
    public function publish(Model $model, array $data): bool
    {
        $response = Http::timeout($this->getTimeout())
            ->withHeaders($this->getHeaders())
            ->send(
                $this->getMethod(),
                $this->getEndpoint($model),
                ['json' => $data]
            );

        return $response->successful();
    }
}