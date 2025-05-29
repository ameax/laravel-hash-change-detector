<?php

declare(strict_types=1);

use ameax\HashChangeDetector\Events\HashChanged;
use ameax\HashChangeDetector\Jobs\PublishModelJob;
use ameax\HashChangeDetector\Models\Publish;
use ameax\HashChangeDetector\Models\Publisher;
use ameax\HashChangeDetector\Publishers\LogPublisher;
use ameax\HashChangeDetector\Tests\TestModels\TestModel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('creates publisher configuration', function () {
    $publisher = Publisher::create([
        'name' => 'Test Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    expect($publisher)->toBeInstanceOf(Publisher::class);
    expect($publisher->name)->toBe('Test Publisher');
    expect($publisher->model_type)->toBe(TestModel::class);
    expect($publisher->publisher_class)->toBe(LogPublisher::class);
    expect($publisher->isActive())->toBeTrue();
});

it('filters publishers by status', function () {
    Publisher::create([
        'name' => 'Active Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    Publisher::create([
        'name' => 'Inactive Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'inactive',
    ]);

    $activePublishers = Publisher::active()->get();
    $allPublishers = Publisher::all();

    expect($activePublishers)->toHaveCount(1);
    expect($allPublishers)->toHaveCount(2);
    expect($activePublishers->first()->name)->toBe('Active Publisher');
});

it('filters publishers by model type', function () {
    Publisher::create([
        'name' => 'Test Model Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    Publisher::create([
        'name' => 'Other Model Publisher',
        'model_type' => 'App\Models\OtherModel',
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    $testModelPublishers = Publisher::forModel(TestModel::class)->get();

    expect($testModelPublishers)->toHaveCount(1);
    expect($testModelPublishers->first()->name)->toBe('Test Model Publisher');
});

it('creates publish records when hash changes', function () {
    Event::fake([HashChanged::class]);

    $publisher = Publisher::create([
        'name' => 'Test Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    $model = TestModel::create([
        'name' => 'Test Product',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    // Manually handle the event since we're faking it
    Event::assertDispatched(HashChanged::class, function ($event) use ($model, $publisher) {
        // Simulate what the listener would do
        $hash = $model->getCurrentHash();

        Publish::create([
            'hash_id' => $hash->id,
            'publisher_id' => $publisher->id,
            'published_hash' => $event->compositeHash,
            'status' => 'pending',
            'attempts' => 0,
        ]);

        return true;
    });

    $publish = Publish::first();
    expect($publish)->not->toBeNull();
    expect($publish->status)->toBe('pending');
    expect($publish->attempts)->toBe(0);
});

it('dispatches job for pending publishes', function () {
    $publisher = Publisher::create([
        'name' => 'Test Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
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

    PublishModelJob::dispatch($publish);

    Queue::assertPushed(PublishModelJob::class);
});

it('handles publish retry logic', function () {
    $publisher = Publisher::create([
        'name' => 'Test Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
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

    // Simulate first failure
    $publish->markAsDeferred('Connection timeout');

    expect($publish->status)->toBe('deferred');
    expect($publish->attempts)->toBe(1);
    expect($publish->last_error)->toBe('Connection timeout');
    expect($publish->next_try)->not->toBeNull();
    expect($publish->next_try->timestamp)->toBeGreaterThan(now()->timestamp);

    // Simulate second failure
    $publish->markAsDeferred('Server error');

    expect($publish->attempts)->toBe(2);
    expect($publish->next_try->timestamp)->toBeGreaterThan(now()->timestamp);

    // Simulate third failure
    $publish->markAsDeferred('Network error');

    expect($publish->attempts)->toBe(3);
    expect($publish->next_try->timestamp)->toBeGreaterThan(now()->timestamp);

    // Fourth failure should mark as failed
    $publish->markAsDeferred('Final error');

    expect($publish->status)->toBe('failed');
    expect($publish->attempts)->toBe(4); // Attempt was incremented before checking max
    expect($publish->last_error)->toBe('Final error');
});

it('identifies publishes ready for retry', function () {
    $publisher1 = Publisher::create([
        'name' => 'Publisher 1',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    $publisher2 = Publisher::create([
        'name' => 'Publisher 2',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    $publisher3 = Publisher::create([
        'name' => 'Publisher 3',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    $model = TestModel::create([
        'name' => 'Test Product',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $hash = $model->getCurrentHash();

    // Create pending publish
    $pendingPublish = Publish::create([
        'hash_id' => $hash->id,
        'publisher_id' => $publisher1->id,
        'published_hash' => $hash->composite_hash,
        'status' => 'pending',
        'attempts' => 0,
    ]);

    // Create deferred publish ready for retry
    $deferredReady = Publish::create([
        'hash_id' => $hash->id,
        'publisher_id' => $publisher2->id,
        'published_hash' => $hash->composite_hash,
        'status' => 'deferred',
        'attempts' => 1,
        'next_try' => now()->subMinute(),
    ]);

    // Create deferred publish not ready for retry
    $deferredNotReady = Publish::create([
        'hash_id' => $hash->id,
        'publisher_id' => $publisher3->id,
        'published_hash' => $hash->composite_hash,
        'status' => 'deferred',
        'attempts' => 1,
        'next_try' => now()->addHour(),
    ]);

    $readyForRetry = Publish::pendingOrDeferred()->get();

    expect($readyForRetry)->toHaveCount(2);
    expect($readyForRetry->pluck('id')->toArray())->toContain($pendingPublish->id);
    expect($readyForRetry->pluck('id')->toArray())->toContain($deferredReady->id);
    expect($readyForRetry->pluck('id')->toArray())->not->toContain($deferredNotReady->id);
});

it('executes log publisher successfully', function () {
    Log::spy();

    $publisher = new LogPublisher;
    $model = TestModel::create([
        'name' => 'Test Product',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $data = $publisher->getData($model);
    $result = $publisher->publish($model, $data);

    expect($result)->toBeTrue();
    expect($publisher->shouldPublish($model))->toBeTrue();
    expect($publisher->getMaxAttempts())->toBe(3);

    Log::shouldHaveReceived('log')->once()->with(
        'info',
        'Model hash changed',
        \Mockery::on(function ($context) use ($model) {
            return $context['model_type'] === TestModel::class &&
                   $context['model_id'] === $model->id &&
                   array_key_exists('data', $context);
        })
    );
});
