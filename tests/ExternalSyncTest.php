<?php

declare(strict_types=1);

use ameax\HashChangeDetector\Events\HashChanged;
use ameax\HashChangeDetector\Events\HashUpdatedWithoutPublishing;
use ameax\HashChangeDetector\Models\Publisher;
use ameax\HashChangeDetector\Tests\TestModels\TestModel;
use ameax\HashChangeDetector\Traits\SyncsFromExternalSources;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

// Create a test model that can sync from external sources
class ExternalSyncTestModel extends TestModel
{
    use SyncsFromExternalSources;

    protected $table = 'test_models'; // Use same table as TestModel

    // Override to prevent relation loading issues in tests
    public function getHashableRelations(): array
    {
        return [];
    }
}

beforeEach(function () {
    // Create some test publishers
    DB::table('publishers')->insert([
        [
            'id' => 1,
            'name' => 'external-api',
            'model_type' => ExternalSyncTestModel::class,
            'publisher_class' => 'App\Publishers\ExternalApiPublisher',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => 2,
            'name' => 'internal-sync',
            'model_type' => ExternalSyncTestModel::class,
            'publisher_class' => 'App\Publishers\InternalSyncPublisher',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);
});

it('updates hash without triggering publishers', function () {
    // Create model first without event tracking
    $model = ExternalSyncTestModel::create([
        'name' => 'Original',
        'description' => 'Original description',
        'price' => 100.00,
        'active' => true,
    ]);

    // Now start tracking events
    Event::fake([HashChanged::class, HashUpdatedWithoutPublishing::class]);

    // Update normally - should trigger HashChanged
    $model->update(['name' => 'Updated']);
    Event::assertDispatched(HashChanged::class);
    Event::assertDispatchedTimes(HashChanged::class, 1);

    // Disable model events temporarily
    $dispatcher = ExternalSyncTestModel::getEventDispatcher();
    ExternalSyncTestModel::unsetEventDispatcher();

    // Update without triggering model events
    $model->update(['name' => 'External Update', 'price' => 200.00]);

    // Re-enable events
    ExternalSyncTestModel::setEventDispatcher($dispatcher);

    // Now update hash without publishing
    $model->updateHashWithoutPublishing();

    // Should have dispatched the special event
    Event::assertDispatched(HashUpdatedWithoutPublishing::class);

    // Should still only have 1 HashChanged event (from the normal update)
    Event::assertDispatchedTimes(HashChanged::class, 1);
});

it('marks specific publisher as synced', function () {
    Event::fake(); // Prevent normal hash change events

    $model = ExternalSyncTestModel::create([
        'name' => 'Test Product',
        'description' => 'Description',
        'price' => 50.00,
        'active' => true,
    ]);

    // Update from external API
    $model->fill(['price' => 75.00]);
    $model->save();

    // This should update hash and mark publisher as synced
    $model->updateHashWithoutPublishing('external-api');

    // Get the updated hash
    $hash = $model->getCurrentHash();

    // Check that external-api publisher is marked as synced
    $publish = DB::table('publishes')
        ->where('hash_id', $hash->id)
        ->where('publisher_id', 1)
        ->first();

    expect($publish)->not->toBeNull();
    expect($publish->status)->toBe('published');
    expect($publish->published_at)->not->toBeNull();

    // Check that other publisher is NOT marked as synced
    $otherPublish = DB::table('publishes')
        ->where('hash_id', $hash->id)
        ->where('publisher_id', 2)
        ->first();

    expect($otherPublish)->toBeNull();
});

it('marks all publishers as synced when no specific publisher provided', function () {
    Event::fake(); // Prevent normal hash change events

    $model = ExternalSyncTestModel::create([
        'name' => 'Test Product',
        'description' => 'Description',
        'price' => 50.00,
        'active' => true,
    ]);

    // Update without specifying publisher
    $model->fill(['price' => 75.00]);
    $model->save();
    $model->updateHashWithoutPublishing(); // No publisher specified

    // Get the updated hash
    $hash = $model->getCurrentHash();

    // Check that all publishers are marked as synced
    $publishes = DB::table('publishes')
        ->where('hash_id', $hash->id)
        ->get();

    expect($publishes)->toHaveCount(2);
    foreach ($publishes as $publish) {
        expect($publish->status)->toBe('published');
        expect($publish->published_at)->not->toBeNull();
    }
});

it('syncs from external data using helper method', function () {
    Event::fake([HashChanged::class, HashUpdatedWithoutPublishing::class]);

    $model = ExternalSyncTestModel::create([
        'name' => 'Original',
        'description' => 'Original',
        'price' => 100.00,
        'active' => true,
    ]);

    // Sync from external API
    $model->syncFromExternal([
        'name' => 'Updated from API',
        'price' => 150.00,
    ], 'external-api');

    // Model should be updated
    expect($model->name)->toBe('Updated from API');
    expect((float) $model->price)->toBe(150.00);

    // Should fire special event, not regular HashChanged
    Event::assertDispatched(HashUpdatedWithoutPublishing::class);
    Event::assertDispatchedTimes(HashChanged::class, 1); // Only from create

    // Check publisher is marked as synced
    $hash = $model->getCurrentHash();
    $publish = DB::table('publishes')
        ->where('hash_id', $hash->id)
        ->where('publisher_id', 1)
        ->first();

    expect($publish->status)->toBe('published');
});

it('creates or updates from external data', function () {
    // Create new model
    $model = ExternalSyncTestModel::syncOrCreateFromExternal(
        ['name' => 'New Product'],
        [
            'description' => 'From API',
            'price' => 99.99,
            'active' => true,
        ],
        'external-api'
    );

    expect($model->exists)->toBeTrue();
    expect($model->description)->toBe('From API');

    // Update existing model
    $updated = ExternalSyncTestModel::syncOrCreateFromExternal(
        ['name' => 'New Product'],
        [
            'price' => 149.99,
        ],
        'external-api'
    );

    expect($updated->id)->toBe($model->id);
    expect((float) $updated->price)->toBe(149.99);

    // Check publisher is marked as synced
    $hash = $updated->getCurrentHash();
    $publish = DB::table('publishes')
        ->where('hash_id', $hash->id)
        ->where('publisher_id', 1)
        ->first();

    expect($publish->status)->toBe('published');
});

it('handles bulk sync from external data', function () {
    $externalData = [
        ['id' => 1, 'name' => 'Product 1', 'price' => 10.00, 'description' => 'Desc 1', 'active' => true],
        ['id' => 2, 'name' => 'Product 2', 'price' => 20.00, 'description' => 'Desc 2', 'active' => true],
        ['id' => 3, 'name' => 'Product 3', 'price' => 30.00, 'description' => 'Desc 3', 'active' => false],
    ];

    $synced = ExternalSyncTestModel::bulkSyncFromExternal($externalData, 'id', 'external-api');

    expect($synced)->toHaveCount(3);

    // Verify all models were created/updated
    foreach ($externalData as $data) {
        $model = ExternalSyncTestModel::find($data['id']);
        expect($model)->not->toBeNull();
        expect($model->name)->toBe($data['name']);
        expect((float) $model->price)->toBe((float) $data['price']);

        // Check publisher is marked as synced
        $hash = $model->getCurrentHash();
        $publish = DB::table('publishes')
            ->where('hash_id', $hash->id)
            ->where('publisher_id', 1)
            ->first();

        expect($publish->status)->toBe('published');
    }
});
