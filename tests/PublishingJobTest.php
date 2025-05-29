<?php

declare(strict_types=1);

use ameax\HashChangeDetector\Jobs\PublishModelJob;
use ameax\HashChangeDetector\Models\Publish;
use ameax\HashChangeDetector\Models\Publisher;
use ameax\HashChangeDetector\Publishers\BasePublisher;
use ameax\HashChangeDetector\Tests\TestModels\TestModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Queue;

// Create a test publisher that we can control
class TestPublisher extends BasePublisher
{
    public static bool $shouldSucceed = true;

    public static bool $shouldPublish = true;

    public static array $publishedData = [];

    public function publish(Model $model, array $data): bool
    {
        self::$publishedData[] = [
            'model' => $model,
            'data' => $data,
        ];

        return self::$shouldSucceed;
    }

    public function shouldPublish(Model $model): bool
    {
        return self::$shouldPublish;
    }
}

beforeEach(function () {
    Queue::fake();
    TestPublisher::$shouldSucceed = true;
    TestPublisher::$shouldPublish = true;
    TestPublisher::$publishedData = [];
});

it('publishes model successfully', function () {
    $publisher = Publisher::create([
        'name' => 'Test Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => TestPublisher::class,
        'status' => 'active',
    ]);

    $model = TestModel::create([
        'name' => 'Test Product',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $hash = $model->getCurrentHash();

    $publish = Publish::create([
        'hash_id' => $hash->id,
        'publisher_id' => $publisher->id,
        'published_hash' => $hash->composite_hash,
        'status' => 'pending',
        'attempts' => 0,
    ]);

    // Execute the job synchronously
    $job = new PublishModelJob($publish);
    $job->handle();

    $publish->refresh();

    expect($publish->status)->toBe('published');
    expect($publish->published_at)->not->toBeNull();
    expect($publish->last_error)->toBeNull();
    expect(TestPublisher::$publishedData)->toHaveCount(1);
    expect(TestPublisher::$publishedData[0]['model']->id)->toBe($model->id);
});

it('handles publisher failure', function () {
    TestPublisher::$shouldSucceed = false;

    $publisher = Publisher::create([
        'name' => 'Test Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => TestPublisher::class,
        'status' => 'active',
    ]);

    $model = TestModel::create([
        'name' => 'Test Product',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $hash = $model->getCurrentHash();

    $publish = Publish::create([
        'hash_id' => $hash->id,
        'publisher_id' => $publisher->id,
        'published_hash' => $hash->composite_hash,
        'status' => 'pending',
        'attempts' => 0,
    ]);

    // Execute the job
    $job = new PublishModelJob($publish);
    $job->handle();

    $publish->refresh();

    expect($publish->status)->toBe('deferred');
    expect($publish->attempts)->toBe(1);
    expect($publish->last_error)->toBe('Publisher returned false');
    expect($publish->next_try)->not->toBeNull();

    // Check that retry job was dispatched
    Queue::assertPushed(PublishModelJob::class);
});

it('skips publishing when shouldPublish returns false', function () {
    TestPublisher::$shouldPublish = false;

    $publisher = Publisher::create([
        'name' => 'Test Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => TestPublisher::class,
        'status' => 'active',
    ]);

    $model = TestModel::create([
        'name' => 'Test Product',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $hash = $model->getCurrentHash();

    $publish = Publish::create([
        'hash_id' => $hash->id,
        'publisher_id' => $publisher->id,
        'published_hash' => $hash->composite_hash,
        'status' => 'pending',
        'attempts' => 0,
    ]);

    // Execute the job
    $job = new PublishModelJob($publish);
    $job->handle();

    $publish->refresh();

    expect($publish->status)->toBe('published');
    expect($publish->published_at)->not->toBeNull();
    expect(TestPublisher::$publishedData)->toHaveCount(0); // No actual publishing happened
});

it('marks as failed after max attempts', function () {
    TestPublisher::$shouldSucceed = false;

    $publisher = Publisher::create([
        'name' => 'Test Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => TestPublisher::class,
        'status' => 'active',
    ]);

    $model = TestModel::create([
        'name' => 'Test Product',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $hash = $model->getCurrentHash();

    $publish = Publish::create([
        'hash_id' => $hash->id,
        'publisher_id' => $publisher->id,
        'published_hash' => $hash->composite_hash,
        'status' => 'deferred',
        'attempts' => 3, // Already at max attempts
        'last_error' => 'Previous error',
    ]);

    // Execute the job
    $job = new PublishModelJob($publish);
    $job->handle();

    $publish->refresh();

    expect($publish->status)->toBe('failed');
    expect($publish->attempts)->toBe(3);
    expect($publish->last_error)->toBe('Publisher returned false');

    // No retry job should be dispatched
    Queue::assertNotPushed(PublishModelJob::class);
});

it('handles missing model gracefully', function () {
    $publisher = Publisher::create([
        'name' => 'Test Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => TestPublisher::class,
        'status' => 'active',
    ]);

    $model = TestModel::create([
        'name' => 'Test Product',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $hash = $model->getCurrentHash();

    $publish = Publish::create([
        'hash_id' => $hash->id,
        'publisher_id' => $publisher->id,
        'published_hash' => $hash->composite_hash,
        'status' => 'pending',
        'attempts' => 0,
    ]);

    // Delete the model to simulate missing data
    $model->forceDelete();

    // Execute the job
    $job = new PublishModelJob($publish);
    $job->handle();

    $publish->refresh();

    expect($publish->status)->toBe('deferred');
    expect($publish->last_error)->toContain('Attempt to read property');
});
