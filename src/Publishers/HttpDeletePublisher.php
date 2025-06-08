<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Publishers;

use ameax\HashChangeDetector\Contracts\DeletePublisher;
use Illuminate\Support\Facades\Http;

class HttpDeletePublisher implements DeletePublisher
{
    protected string $baseUrl;

    protected array $headers;

    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('laravel-hash-change-detector.publishers.http.base_url', '');
        $this->headers = config('laravel-hash-change-detector.publishers.http.headers', []);
        $this->timeout = config('laravel-hash-change-detector.publishers.http.timeout', 30);
    }

    /**
     * Publish the deletion of a model to external HTTP endpoint.
     */
    public function publishDeletion(string $modelClass, int $modelId, array $lastKnownData): bool
    {
        $endpoint = $this->getEndpoint($modelClass);
        $url = rtrim($this->baseUrl, '/').'/'.ltrim($endpoint, '/');

        $response = Http::timeout($this->timeout)
            ->withHeaders($this->headers)
            ->delete("{$url}/{$modelId}", [
                'deleted_at' => $lastKnownData['deleted_at'] ?? now()->toIso8601String(),
                'last_known_hash' => $lastKnownData['attribute_hash'] ?? null,
            ]);

        return $response->successful();
    }

    /**
     * Determine if the deletion should be published.
     */
    public function shouldPublishDeletion(string $modelClass, int $modelId): bool
    {
        // You could add logic here to skip certain models or IDs
        return true;
    }

    /**
     * Get the maximum number of retry attempts for deletion publishing.
     */
    public function getMaxAttempts(): int
    {
        return config('laravel-hash-change-detector.publishers.http.max_attempts', 3);
    }

    /**
     * Get the endpoint for a given model class.
     */
    protected function getEndpoint(string $modelClass): string
    {
        // Convert model class to endpoint
        // e.g., App\Models\User -> users
        $basename = class_basename($modelClass);

        return strtolower($basename).'s';
    }
}
