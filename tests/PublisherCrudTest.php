<?php

declare(strict_types=1);

use ameax\HashChangeDetector\Models\Publish;
use ameax\HashChangeDetector\Models\Publisher;
use ameax\HashChangeDetector\Publishers\LogPublisher;
use ameax\HashChangeDetector\Tests\TestModels\TestModel;

beforeEach(function () {
    // Register API routes for testing
    Route::prefix('api/hash-change-detector')
        ->group(__DIR__.'/../routes/api.php');
});

it('lists publishers with pagination', function () {
    // Create multiple publishers
    Publisher::factory()->count(25)->create();

    $response = $this->getJson('/api/hash-change-detector/publishers?per_page=10');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'model_type', 'publisher_class', 'status'],
            ],
            'current_page',
            'total',
            'per_page',
        ])
        ->assertJsonCount(10, 'data');
});

it('filters publishers by model type', function () {
    Publisher::create([
        'name' => 'Test Publisher 1',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    Publisher::create([
        'name' => 'Test Publisher 2',
        'model_type' => 'App\Models\User',
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    $response = $this->getJson('/api/hash-change-detector/publishers?model_type='.urlencode(TestModel::class));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJson([
            'data' => [
                ['model_type' => TestModel::class],
            ],
        ]);
});

it('searches publishers by name', function () {
    Publisher::create([
        'name' => 'WebHook Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
    ]);

    Publisher::create([
        'name' => 'Log Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
    ]);

    $response = $this->getJson('/api/hash-change-detector/publishers?search=hook');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJson([
            'data' => [
                ['name' => 'WebHook Publisher'],
            ],
        ]);
});

it('creates a new publisher', function () {
    $response = $this->postJson('/api/hash-change-detector/publishers', [
        'name' => 'New Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
        'config' => ['level' => 'info'],
    ]);

    $response->assertCreated()
        ->assertJson([
            'name' => 'New Publisher',
            'model_type' => TestModel::class,
            'publisher_class' => LogPublisher::class,
            'status' => 'active',
            'config' => ['level' => 'info'],
        ]);

    expect(Publisher::where('name', 'New Publisher')->exists())->toBeTrue();
});

it('validates unique publisher name', function () {
    Publisher::create([
        'name' => 'Existing Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
    ]);

    $response = $this->postJson('/api/hash-change-detector/publishers', [
        'name' => 'Existing Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('validates model class exists', function () {
    $response = $this->postJson('/api/hash-change-detector/publishers', [
        'name' => 'Test Publisher',
        'model_type' => 'NonExistent\Model',
        'publisher_class' => LogPublisher::class,
    ]);

    $response->assertStatus(422)
        ->assertJson(['error' => 'Model class does not exist']);
});

it('validates publisher class exists and implements contract', function () {
    $response = $this->postJson('/api/hash-change-detector/publishers', [
        'name' => 'Test Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => 'NonExistent\Publisher',
    ]);

    $response->assertStatus(422)
        ->assertJson(['error' => 'Publisher class does not exist']);
});

it('shows a publisher with statistics', function () {
    $publisher = Publisher::create([
        'name' => 'Test Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
    ]);

    // Create test model without events to avoid conflicts
    $dispatcher = TestModel::getEventDispatcher();
    TestModel::unsetEventDispatcher();

    $model1 = TestModel::create(['name' => 'Test 1']);
    $model2 = TestModel::create(['name' => 'Test 2']);

    TestModel::setEventDispatcher($dispatcher);

    // Manually create hashes
    $hash1 = \ameax\HashChangeDetector\Models\Hash::create([
        'hashable_type' => TestModel::class,
        'hashable_id' => $model1->id,
        'attribute_hash' => md5('Test 1'),
        'composite_hash' => md5('Test 1'),
    ]);

    $hash2 = \ameax\HashChangeDetector\Models\Hash::create([
        'hashable_type' => TestModel::class,
        'hashable_id' => $model2->id,
        'attribute_hash' => md5('Test 2'),
        'composite_hash' => md5('Test 2'),
    ]);

    Publish::create([
        'hash_id' => $hash1->id,
        'publisher_id' => $publisher->id,
        'published_hash' => $hash1->attribute_hash,
        'status' => 'published',
        'published_at' => now(),
    ]);

    Publish::create([
        'hash_id' => $hash2->id,
        'publisher_id' => $publisher->id,
        'published_hash' => $hash2->attribute_hash,
        'status' => 'failed',
        'last_error' => 'Test error',
    ]);

    $response = $this->getJson("/api/hash-change-detector/publishers/{$publisher->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'publisher' => ['id', 'name', 'model_type', 'publisher_class', 'status'],
            'stats' => [
                'total_publishes',
                'published',
                'failed',
                'pending',
                'deferred',
                'last_published_at',
            ],
        ])
        ->assertJson([
            'stats' => [
                'total_publishes' => 2,
                'published' => 1,
                'failed' => 1,
            ],
        ]);
});

it('updates a publisher', function () {
    $publisher = Publisher::create([
        'name' => 'Old Name',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    $response = $this->putJson("/api/hash-change-detector/publishers/{$publisher->id}", [
        'name' => 'New Name',
        'status' => 'inactive',
        'config' => ['level' => 'debug'],
    ]);

    $response->assertOk()
        ->assertJson([
            'name' => 'New Name',
            'status' => 'inactive',
            'config' => ['level' => 'debug'],
        ]);

    $publisher->refresh();
    expect($publisher->name)->toBe('New Name');
    expect($publisher->status)->toBe('inactive');
});

it('deletes a publisher without pending publishes', function () {
    $publisher = Publisher::create([
        'name' => 'Test Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
    ]);

    $response = $this->deleteJson("/api/hash-change-detector/publishers/{$publisher->id}");

    $response->assertOk()
        ->assertJson(['message' => 'Publisher deleted successfully']);

    expect(Publisher::find($publisher->id))->toBeNull();
});

it('prevents deleting publisher with pending publishes', function () {
    $publisher = Publisher::create([
        'name' => 'Test Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
    ]);

    // Create model without events
    $dispatcher = TestModel::getEventDispatcher();
    TestModel::unsetEventDispatcher();
    $model = TestModel::create(['name' => 'Test']);
    TestModel::setEventDispatcher($dispatcher);

    // Manually create hash
    $hash = \ameax\HashChangeDetector\Models\Hash::create([
        'hashable_type' => TestModel::class,
        'hashable_id' => $model->id,
        'attribute_hash' => md5('Test'),
        'composite_hash' => md5('Test'),
    ]);

    Publish::create([
        'hash_id' => $hash->id,
        'publisher_id' => $publisher->id,
        'published_hash' => $hash->attribute_hash,
        'status' => 'pending',
    ]);

    $response = $this->deleteJson("/api/hash-change-detector/publishers/{$publisher->id}");

    $response->assertStatus(409)
        ->assertJson([
            'error' => 'Cannot delete publisher with pending publishes',
            'pending_count' => 1,
        ]);
});

it('bulk updates publishers', function () {
    $publishers = Publisher::factory()->count(3)->create(['status' => 'active']);

    $response = $this->patchJson('/api/hash-change-detector/publishers/bulk', [
        'publisher_ids' => $publishers->pluck('id')->toArray(),
        'status' => 'inactive',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Publishers updated successfully',
            'updated_count' => 3,
        ]);

    foreach ($publishers as $publisher) {
        expect($publisher->fresh()->status)->toBe('inactive');
    }
});

it('gets available publisher types', function () {
    $response = $this->getJson('/api/hash-change-detector/publishers/types');

    $response->assertOk()
        ->assertJsonStructure([
            'types' => [
                '*' => ['class', 'name', 'description'],
            ],
        ])
        ->assertJson([
            'types' => [
                [
                    'class' => LogPublisher::class,
                    'name' => 'Log Publisher',
                ],
            ],
        ]);
});

it('tests a publisher configuration', function () {
    $publisher = Publisher::create([
        'name' => 'Test Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);

    $model = TestModel::create(['name' => 'Test Model']);

    $response = $this->postJson('/api/hash-change-detector/publishers/test', [
        'publisher_id' => $publisher->id,
        'model_id' => $model->id,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'result' => [
                'should_publish',
                'data_preview',
                'publisher_class',
            ],
        ])
        ->assertJson([
            'result' => [
                'should_publish' => true,
                'publisher_class' => LogPublisher::class,
            ],
        ]);
});

it('gets publisher statistics', function () {
    $publisher = Publisher::create([
        'name' => 'Test Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
    ]);

    // Create models without events
    $dispatcher = TestModel::getEventDispatcher();
    TestModel::unsetEventDispatcher();

    $model1 = TestModel::create(['name' => 'Test 1']);
    $model2 = TestModel::create(['name' => 'Test 2']);

    TestModel::setEventDispatcher($dispatcher);

    // Manually create hashes
    $hash1 = \ameax\HashChangeDetector\Models\Hash::create([
        'hashable_type' => TestModel::class,
        'hashable_id' => $model1->id,
        'attribute_hash' => md5('Test 1'),
        'composite_hash' => md5('Test 1'),
    ]);

    $hash2 = \ameax\HashChangeDetector\Models\Hash::create([
        'hashable_type' => TestModel::class,
        'hashable_id' => $model2->id,
        'attribute_hash' => md5('Test 2'),
        'composite_hash' => md5('Test 2'),
    ]);

    // Today
    Publish::create([
        'hash_id' => $hash1->id,
        'publisher_id' => $publisher->id,
        'published_hash' => $hash1->attribute_hash,
        'status' => 'published',
        'published_at' => now(),
        'created_at' => now(),
    ]);

    // Yesterday
    Publish::create([
        'hash_id' => $hash2->id,
        'publisher_id' => $publisher->id,
        'published_hash' => $hash2->attribute_hash,
        'status' => 'failed',
        'created_at' => now()->subDay(),
    ]);

    $response = $this->getJson("/api/hash-change-detector/publishers/{$publisher->id}/stats?period=7days");

    $response->assertOk()
        ->assertJsonStructure([
            'publisher' => ['id', 'name', 'status'],
            'period',
            'stats' => [
                'by_status',
                'by_day',
                'avg_processing_seconds',
                'success_rate',
            ],
        ])
        ->assertJson([
            'period' => '7days',
            'stats' => [
                'success_rate' => 50.0,
            ],
        ]);
});
