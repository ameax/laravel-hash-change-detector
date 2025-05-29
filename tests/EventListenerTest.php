<?php

declare(strict_types=1);

use ameax\HashChangeDetector\Events\HashChanged;
use ameax\HashChangeDetector\Jobs\PublishModelJob;
use ameax\HashChangeDetector\Listeners\HandleHashChanged;
use ameax\HashChangeDetector\Models\Publish;
use ameax\HashChangeDetector\Models\Publisher;
use ameax\HashChangeDetector\Publishers\LogPublisher;
use ameax\HashChangeDetector\Tests\TestModels\TestModel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('creates publish records when hash changes', function () {
    $publisher = Publisher::create([
        'name' => 'Event Test Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    $model = TestModel::create([
        'name' => 'Event Test Product',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $hash = $model->getCurrentHash();

    // Create event
    $event = new HashChanged(
        $model,
        $hash->attribute_hash,
        $hash->composite_hash
    );

    // Handle event
    $listener = new HandleHashChanged;
    $listener->handle($event);

    // Check publish record was created
    $publishes = Publish::where('hash_id', $hash->id)->get();

    expect($publishes)->toHaveCount(1);
    expect($publishes->first()->publisher_id)->toBe($publisher->id);
    expect($publishes->first()->status)->toBe('pending');
});

it('dispatches jobs for each active publisher', function () {
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

    $inactivePublisher = Publisher::create([
        'name' => 'Inactive Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'inactive',
    ]);

    $model = TestModel::create([
        'name' => 'Multi Publisher Test',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $hash = $model->getCurrentHash();

    // Create and handle event
    $event = new HashChanged(
        $model,
        $hash->attribute_hash,
        $hash->composite_hash
    );

    $listener = new HandleHashChanged;
    $listener->handle($event);

    // Check only active publishers got jobs
    Queue::assertPushed(PublishModelJob::class, 2);

    $publishes = Publish::all();
    expect($publishes)->toHaveCount(2);
    expect($publishes->pluck('publisher_id')->sort()->values())->toEqual(collect([$publisher1->id, $publisher2->id])->sort()->values());
});

it('handles event with no publishers gracefully', function () {
    $model = TestModel::create([
        'name' => 'No Publisher Test',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $hash = $model->getCurrentHash();

    // Create and handle event
    $event = new HashChanged(
        $model,
        $hash->attribute_hash,
        $hash->composite_hash
    );

    $listener = new HandleHashChanged;
    $listener->handle($event);

    // No jobs should be dispatched
    Queue::assertNotPushed(PublishModelJob::class);

    expect(Publish::count())->toBe(0);
});

it('respects queue configuration for jobs', function () {
    config(['hash-change-detector.queue' => 'custom-event-queue']);

    $publisher = Publisher::create([
        'name' => 'Queue Test Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    $model = TestModel::create([
        'name' => 'Queue Test Product',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $hash = $model->getCurrentHash();

    // Create and handle event
    $event = new HashChanged(
        $model,
        $hash->attribute_hash,
        $hash->composite_hash
    );

    $listener = new HandleHashChanged;
    $listener->handle($event);

    // Check job was dispatched
    Queue::assertPushed(PublishModelJob::class);

    // Reset config
    config(['hash-change-detector.queue' => null]);
});

it('event contains correct data', function () {
    $model = TestModel::create([
        'name' => 'Event Data Test',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $newHash = $model->calculateAttributeHash();
    $newComposite = $model->calculateCompositeHash();

    $event = new HashChanged(
        $model,
        $newHash,
        $newComposite
    );

    expect($event->model)->toBe($model);
    expect($event->attributeHash)->toBe($newHash);
    expect($event->compositeHash)->toBe($newComposite);
});

it('fires event when model is created', function () {
    Event::fake([HashChanged::class]);

    $model = TestModel::create([
        'name' => 'Event Fire Test',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    Event::assertDispatched(HashChanged::class, function ($event) use ($model) {
        return $event->model->id === $model->id &&
               $event->attributeHash !== null;
    });
});

it('fires event when model is updated', function () {
    Event::fake([HashChanged::class]);

    $model = TestModel::create([
        'name' => 'Original Name',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    // Reset event fake for clean test
    Event::fake([HashChanged::class]);

    $model->update(['name' => 'Updated Name']);

    Event::assertDispatched(HashChanged::class, function ($event) use ($model) {
        return $event->model->id === $model->id &&
               $event->attributeHash !== null;
    });
});
