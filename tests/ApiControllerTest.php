<?php

declare(strict_types=1);

use ameax\HashChangeDetector\Http\Controllers\HashChangeDetectorApiController;
use ameax\HashChangeDetector\Jobs\DetectChangesJob;
use ameax\HashChangeDetector\Jobs\PublishModelJob;
use ameax\HashChangeDetector\Models\Publish;
use ameax\HashChangeDetector\Models\Publisher;
use ameax\HashChangeDetector\Publishers\LogPublisher;
use ameax\HashChangeDetector\Tests\TestModels\TestModel;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Register API routes for testing
    Route::prefix('api/hash-change-detector')
        ->group(__DIR__.'/../routes/api.php');

    // Clear queue
    Queue::fake();
});

it('gets model hash information', function () {
    $model = TestModel::create([
        'name' => 'Test Model',
        'description' => 'Description',
    ]);

    $response = $this->getJson("/api/hash-change-detector/models/TestModel/{$model->id}/hash");

    $response->assertOk()
        ->assertJsonStructure([
            'model_type',
            'model_id',
            'attribute_hash',
            'composite_hash',
            'is_main_model',
            'parent_model',
            'updated_at',
        ])
        ->assertJson([
            'model_id' => $model->id,
            'is_main_model' => true,
        ]);
});

it('returns 404 for non-existent model hash', function () {
    $response = $this->getJson('/api/hash-change-detector/models/InvalidModel/999/hash');

    $response->assertNotFound()
        ->assertJson(['error' => 'Model type not found']);
});

it('forces publish for a model', function () {
    $model = TestModel::create([
        'name' => 'Test Model',
        'description' => 'Description',
    ]);

    $publisher = Publisher::create([
        'name' => 'Test Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    $response = $this->postJson("/api/hash-change-detector/models/TestModel/{$model->id}/publish", [
        'publisher_ids' => [$publisher->id],
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'model' => ['type', 'id'],
            'publishers' => [
                '*' => ['publisher_id', 'publisher_name', 'publish_id'],
            ],
        ]);

    // Verify job was dispatched
    Queue::assertPushed(PublishModelJob::class);

    // Verify publish record was created
    expect(Publish::count())->toBe(1);
    expect(Publish::first()->status)->toBe('pending');
});

it('forces publish using publisher names', function () {
    $model = TestModel::create([
        'name' => 'Test Model',
        'description' => 'Description',
    ]);

    $publisher = Publisher::create([
        'name' => 'log-publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    $response = $this->postJson("/api/hash-change-detector/models/TestModel/{$model->id}/publish", [
        'publisher_names' => ['log-publisher'],
    ]);

    $response->assertOk();
    Queue::assertPushed(PublishModelJob::class);
});

it('gets publish history for a model', function () {
    $model = TestModel::create([
        'name' => 'Test Model',
        'description' => 'Description',
    ]);

    $publisher = Publisher::create([
        'name' => 'Test Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    // Create some publish records
    $hash = $model->getCurrentHash();
    Publish::create([
        'hash_id' => $hash->id,
        'publisher_id' => $publisher->id,
        'published_hash' => $hash->attribute_hash,
        'status' => 'published',
        'published_at' => now(),
        'attempts' => 1,
    ]);

    // Create a second publisher for the failed record
    $publisher2 = Publisher::create([
        'name' => 'Test Publisher 2',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    Publish::create([
        'hash_id' => $hash->id,
        'publisher_id' => $publisher2->id,
        'published_hash' => $hash->attribute_hash,
        'status' => 'failed',
        'attempts' => 3,
        'last_error' => 'Connection timeout',
    ]);

    $response = $this->getJson("/api/hash-change-detector/models/TestModel/{$model->id}/publishes");

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'hash_id',
                    'publisher_id',
                    'status',
                    'attempts',
                    'publisher',
                ],
            ],
        ]);
});

it('filters publish history by status', function () {
    $model = TestModel::create([
        'name' => 'Test Model',
        'description' => 'Description',
    ]);

    $publisher = Publisher::create([
        'name' => 'Test Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    $hash = $model->getCurrentHash();

    // Create published record
    Publish::create([
        'hash_id' => $hash->id,
        'publisher_id' => $publisher->id,
        'published_hash' => $hash->attribute_hash,
        'status' => 'published',
        'published_at' => now(),
    ]);

    // Create a second publisher for the failed record
    $publisher2 = Publisher::create([
        'name' => 'Another Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    Publish::create([
        'hash_id' => $hash->id,
        'publisher_id' => $publisher2->id,
        'published_hash' => $hash->attribute_hash,
        'status' => 'failed',
        'last_error' => 'Error',
    ]);

    $response = $this->getJson("/api/hash-change-detector/models/TestModel/{$model->id}/publishes?status=published");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJson([
            'data' => [
                ['status' => 'published'],
            ],
        ]);
});

it('gets all publishers via legacy endpoint', function () {
    Publisher::create([
        'name' => 'Publisher 1',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    Publisher::create([
        'name' => 'Publisher 2',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'inactive',
    ]);

    $response = $this->getJson('/api/hash-change-detector/publishers');

    // The new CRUD controller returns paginated results
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'model_type',
                    'publisher_class',
                    'status',
                ],
            ],
        ]);
});

it('filters publishers by model type', function () {
    Publisher::create([
        'name' => 'Publisher 1',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    Publisher::create([
        'name' => 'Publisher 2',
        'model_type' => 'App\Models\User',
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    $response = $this->getJson('/api/hash-change-detector/publishers?model_type='.urlencode(TestModel::class));

    // The new CRUD controller returns paginated results
    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJson([
            'data' => [
                ['model_type' => TestModel::class],
            ],
        ]);
});

it('triggers change detection', function () {
    $response = $this->postJson('/api/hash-change-detector/detect-changes');

    $response->assertOk()
        ->assertJson([
            'message' => 'Change detection job dispatched',
            'model_type' => 'all',
        ]);

    Queue::assertPushed(DetectChangesJob::class);
});

it('triggers change detection for specific model', function () {
    $response = $this->postJson('/api/hash-change-detector/detect-changes', [
        'model_type' => TestModel::class,
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Change detection job dispatched',
            'model_type' => TestModel::class,
        ]);

    Queue::assertPushed(DetectChangesJob::class);
});

it('gets statistics', function () {
    // Create some test data
    TestModel::create(['name' => 'Model 1']);
    TestModel::create(['name' => 'Model 2']);

    Publisher::create([
        'name' => 'Publisher 1',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    $response = $this->getJson('/api/hash-change-detector/stats');

    $response->assertOk()
        ->assertJsonStructure([
            'total_hashes',
            'main_models',
            'related_models',
            'total_publishers',
            'active_publishers',
            'publishes_by_status',
        ]);
});

it('gets model-specific statistics', function () {
    TestModel::create(['name' => 'Model 1']);
    TestModel::create(['name' => 'Model 2']);

    $response = $this->getJson('/api/hash-change-detector/stats?model_type='.urlencode(TestModel::class));

    $response->assertOk()
        ->assertJsonStructure([
            'total_hashes',
            'main_models',
            'related_models',
            'total_publishers',
            'active_publishers',
            'publishes_by_status',
            'model_specific' => [
                'model_type',
                'total_models',
                'publishers',
            ],
        ])
        ->assertJson([
            'model_specific' => [
                'model_type' => TestModel::class,
                'total_models' => 2,
            ],
        ]);
});

it('retries failed publishes', function () {
    $publisher = Publisher::create([
        'name' => 'Test Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    $model = TestModel::create(['name' => 'Test']);
    $hash = $model->getCurrentHash();

    // Create failed publish
    $publish = Publish::create([
        'hash_id' => $hash->id,
        'publisher_id' => $publisher->id,
        'published_hash' => $hash->attribute_hash,
        'status' => 'failed',
        'attempts' => 3,
        'last_error' => 'Connection error',
    ]);

    $response = $this->postJson('/api/hash-change-detector/retry-publishes');

    $response->assertOk()
        ->assertJson([
            'message' => 'Retry jobs dispatched',
            'count' => 1,
        ]);

    Queue::assertPushed(PublishModelJob::class);

    // Verify publish was reset
    $publish->refresh();
    expect($publish->status)->toBe('pending');
    expect($publish->attempts)->toBe(0);
});

it('updates publisher status', function () {
    $publisher = Publisher::create([
        'name' => 'Test Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    $response = $this->patchJson("/api/hash-change-detector/publishers/{$publisher->id}", [
        'status' => 'inactive',
    ]);

    // The new CRUD controller returns the updated publisher directly
    $response->assertOk()
        ->assertJson([
            'id' => $publisher->id,
            'status' => 'inactive',
            'name' => 'Test Publisher',
        ]);

    $publisher->refresh();
    expect($publisher->status)->toBe('inactive');
});

it('initializes hashes for models', function () {
    // Create models without triggering hash creation
    $dispatcher = TestModel::getEventDispatcher();
    TestModel::unsetEventDispatcher();

    TestModel::create(['name' => 'Model 1']);
    TestModel::create(['name' => 'Model 2']);

    TestModel::setEventDispatcher($dispatcher);

    // Verify no hashes exist
    expect(\ameax\HashChangeDetector\Models\Hash::count())->toBe(0);

    $response = $this->postJson('/api/hash-change-detector/initialize-hashes', [
        'model_type' => TestModel::class,
        'chunk_size' => 10,
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Hash initialization completed',
            'model_type' => TestModel::class,
            'initialized_count' => 2,
        ]);

    // Verify hashes were created
    expect(\ameax\HashChangeDetector\Models\Hash::count())->toBe(2);
});

it('validates required fields for hash initialization', function () {
    $response = $this->postJson('/api/hash-change-detector/initialize-hashes', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['model_type']);
});

it('resolves short model names', function () {
    // This test assumes TestModel could be resolved without full namespace
    // In real usage, this would work with App\Models\User -> User
    $controller = new HashChangeDetectorApiController;

    // Test full class name
    $fullClass = $controller->resolveModelClass(TestModel::class);
    expect($fullClass)->toBe(TestModel::class);

    // Test short name resolution
    $shortClass = $controller->resolveModelClass('TestModel');
    expect($shortClass)->toBe(TestModel::class); // Should resolve to full class
});
