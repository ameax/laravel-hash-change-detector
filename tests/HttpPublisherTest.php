<?php

declare(strict_types=1);

use ameax\HashChangeDetector\Publishers\HttpPublisher;
use ameax\HashChangeDetector\Tests\TestModels\TestModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// Concrete implementation for testing
class TestHttpPublisher extends HttpPublisher
{
    protected function getEndpoint(Model $model): string
    {
        return 'https://api.example.com/products/'.$model->id;
    }

    protected function getMethod(): string
    {
        return 'PUT';
    }

    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer test-token',
            'X-Model-Type' => TestModel::class,
        ];
    }

    public function getData(Model $model): array
    {
        return [
            'id' => $model->id,
            'attributes' => $model->toArray(),
            'hash' => $model->getCurrentHash()->attribute_hash,
        ];
    }
}

// Test different HTTP methods
class PostHttpPublisher extends TestHttpPublisher
{
    protected function getMethod(): string
    {
        return 'POST';
    }

    protected function getEndpoint(Model $model): string
    {
        return 'https://api.example.com/products';
    }
}

class PatchHttpPublisher extends TestHttpPublisher
{
    protected function getMethod(): string
    {
        return 'PATCH';
    }
}

class DeleteHttpPublisher extends TestHttpPublisher
{
    protected function getMethod(): string
    {
        return 'DELETE';
    }

    public function getData(Model $model): array
    {
        return []; // DELETE typically doesn't send body
    }
}

beforeEach(function () {
    Http::fake();
});

it('sends PUT request with correct data', function () {
    Http::fake([
        'api.example.com/*' => Http::response(['success' => true], 200),
    ]);

    $model = TestModel::create([
        'name' => 'HTTP Test Product',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $publisher = new TestHttpPublisher;
    $data = $publisher->getData($model);
    $result = $publisher->publish($model, $data);

    expect($result)->toBeTrue();

    Http::assertSent(function (Request $request) use ($model) {
        return $request->url() === 'https://api.example.com/products/'.$model->id &&
               $request->method() === 'PUT' &&
               $request->hasHeader('Authorization', 'Bearer test-token') &&
               $request->hasHeader('X-Model-Type', TestModel::class) &&
               $request['id'] === $model->id;
    });
});

it('sends POST request correctly', function () {
    Http::fake([
        'api.example.com/*' => Http::response(['id' => 123], 201),
    ]);

    $model = TestModel::create([
        'name' => 'POST Test Product',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $publisher = new PostHttpPublisher;
    $data = $publisher->getData($model);
    $result = $publisher->publish($model, $data);

    expect($result)->toBeTrue();

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api.example.com/products' &&
               $request->method() === 'POST';
    });
});

it('sends PATCH request correctly', function () {
    Http::fake([
        'api.example.com/*' => Http::response(['success' => true], 200),
    ]);

    $model = TestModel::create([
        'name' => 'PATCH Test Product',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $publisher = new PatchHttpPublisher;
    $data = $publisher->getData($model);
    $result = $publisher->publish($model, $data);

    expect($result)->toBeTrue();

    Http::assertSent(function (Request $request) {
        return $request->method() === 'PATCH';
    });
});

it('sends DELETE request without body', function () {
    Http::fake([
        'api.example.com/*' => Http::response(null, 204),
    ]);

    $model = TestModel::create([
        'name' => 'DELETE Test Product',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $publisher = new DeleteHttpPublisher;
    $data = $publisher->getData($model);
    $result = $publisher->publish($model, $data);

    expect($result)->toBeTrue();

    Http::assertSent(function (Request $request) {
        return $request->method() === 'DELETE' &&
               $request->data() === [];
    });
});

it('handles network timeouts', function () {
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
    });

    $model = TestModel::create([
        'name' => 'Timeout Test Product',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $publisher = new TestHttpPublisher;
    $data = $publisher->getData($model);

    expect(fn () => $publisher->publish($model, $data))->toThrow(\Illuminate\Http\Client\ConnectionException::class);
});

it('considers 2xx responses as success', function () {
    $responses = [200, 201, 202, 204];

    foreach ($responses as $status) {
        Http::fake([
            'api.example.com/*' => Http::response(['success' => true], $status),
        ]);

        $model = TestModel::create([
            'name' => "Status $status Test",
            'description' => 'Test Description',
            'price' => 99.99,
            'active' => true,
        ]);

        $publisher = new TestHttpPublisher;
        $data = $publisher->getData($model);
        $result = $publisher->publish($model, $data);

        expect($result)->toBeTrue();
    }
});

it('respects max attempts configuration', function () {
    $publisher = new TestHttpPublisher;

    expect($publisher->getMaxAttempts())->toBe(3);
});

it('can determine if model should be published', function () {
    $model = TestModel::create([
        'name' => 'Should Publish Test',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $publisher = new TestHttpPublisher;

    expect($publisher->shouldPublish($model))->toBeTrue();
});
