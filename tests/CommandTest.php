<?php

declare(strict_types=1);

use ameax\HashChangeDetector\Jobs\DetectChangesJob;
use ameax\HashChangeDetector\Jobs\PublishModelJob;
use ameax\HashChangeDetector\Models\Publish;
use ameax\HashChangeDetector\Models\Publisher;
use ameax\HashChangeDetector\Publishers\LogPublisher;
use ameax\HashChangeDetector\Tests\TestModels\TestModel;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('creates publisher via command', function () {
    $this->artisan('hash-detector:publisher:create', [
        'name' => 'API Publisher',
        'model' => TestModel::class,
        'publisher' => LogPublisher::class,
    ])
        ->expectsOutput("Publisher 'API Publisher' created successfully.")
        ->assertSuccessful();

    $publisher = Publisher::where('name', 'API Publisher')->first();

    expect($publisher)->not->toBeNull();
    expect($publisher->model_type)->toBe(TestModel::class);
    expect($publisher->publisher_class)->toBe(LogPublisher::class);
    expect($publisher->status)->toBe('active');
});

it('creates inactive publisher via command', function () {
    $this->artisan('hash-detector:publisher:create', [
        'name' => 'Inactive Publisher',
        'model' => TestModel::class,
        'publisher' => LogPublisher::class,
        '--inactive' => true,
    ])
        ->assertSuccessful();

    $publisher = Publisher::where('name', 'Inactive Publisher')->first();

    expect($publisher->status)->toBe('inactive');
});

it('validates model class exists', function () {
    $this->artisan('hash-detector:publisher:create', [
        'name' => 'Invalid Publisher',
        'model' => 'App\Models\NonExistentModel',
        'publisher' => LogPublisher::class,
    ])
        ->expectsOutput('Model class App\Models\NonExistentModel does not exist.')
        ->assertFailed();
});

it('lists publishers', function () {
    Publisher::create([
        'name' => 'First Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    Publisher::create([
        'name' => 'Second Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'inactive',
    ]);

    $this->artisan('hash-detector:publisher:list')
        ->expectsTable(
            ['ID', 'Name', 'Model', 'Publisher', 'Status', 'Created At'],
            Publisher::all()->map(fn ($p) => [
                $p->id,
                $p->name,
                'TestModel',
                'LogPublisher',
                $p->status,
                $p->created_at->format('Y-m-d H:i:s'),
            ])
        )
        ->assertSuccessful();
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

    $this->artisan('hash-detector:publisher:list', ['--status' => 'active'])
        ->assertSuccessful();
});

it('toggles publisher status', function () {
    $publisher = Publisher::create([
        'name' => 'Toggle Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    // Toggle to inactive
    $this->artisan('hash-detector:publisher:toggle', ['id' => $publisher->id])
        ->expectsOutput("Publisher 'Toggle Publisher' is now inactive.")
        ->assertSuccessful();

    $publisher->refresh();
    expect($publisher->status)->toBe('inactive');

    // Toggle back to active
    $this->artisan('hash-detector:publisher:toggle', ['id' => $publisher->id])
        ->expectsOutput("Publisher 'Toggle Publisher' is now active.")
        ->assertSuccessful();

    $publisher->refresh();
    expect($publisher->status)->toBe('active');
});

it('activates publisher explicitly', function () {
    $publisher = Publisher::create([
        'name' => 'Inactive Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'inactive',
    ]);

    $this->artisan('hash-detector:publisher:toggle', [
        'id' => $publisher->id,
        '--activate' => true,
    ])
        ->expectsOutput("Publisher 'Inactive Publisher' is now active.")
        ->assertSuccessful();

    $publisher->refresh();
    expect($publisher->status)->toBe('active');
});

it('dispatches detect changes job', function () {
    $this->artisan('hash-detector:detect-changes')
        ->expectsOutput('Detecting changes for all hashable models...')
        ->expectsOutput('Change detection job dispatched.')
        ->assertSuccessful();

    Queue::assertPushed(DetectChangesJob::class);
});

it('dispatches detect changes job for specific model', function () {
    $this->artisan('hash-detector:detect-changes', ['model' => TestModel::class])
        ->expectsOutput('Detecting changes for '.TestModel::class.'...')
        ->expectsOutput('Change detection job dispatched.')
        ->assertSuccessful();

    Queue::assertPushed(DetectChangesJob::class);
});

it('retries deferred publishes', function () {
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

    // Create deferred publish ready for retry
    Publish::create([
        'hash_id' => $hash->id,
        'publisher_id' => $publisher->id,
        'published_hash' => $hash->composite_hash,
        'status' => 'deferred',
        'attempts' => 1,
        'next_try' => now()->subMinute(),
    ]);

    $this->artisan('hash-detector:retry-publishes')
        ->expectsOutput('Dispatched 1 publish jobs.')
        ->assertSuccessful();

    Queue::assertPushed(PublishModelJob::class, 1);
});

it('handles no publishes to retry', function () {
    $this->artisan('hash-detector:retry-publishes')
        ->expectsOutput('No publishes to retry.')
        ->assertSuccessful();

    Queue::assertNotPushed(PublishModelJob::class);
});
