<?php

declare(strict_types=1);

use ameax\HashChangeDetector\Events\HashableModelDeleted;
use ameax\HashChangeDetector\Jobs\DeletePublishJob;
use ameax\HashChangeDetector\Models\Publish;
use ameax\HashChangeDetector\Models\Publisher;
use ameax\HashChangeDetector\Publishers\LogDeletePublisher;
use ameax\HashChangeDetector\Tests\TestModels\TestModel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('triggers deletion publishers immediately when model is deleted via eloquent', function () {
    // Create a delete publisher
    $publisher = Publisher::create([
        'name' => 'Test Delete Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogDeletePublisher::class,
        'status' => 'active',
    ]);

    // Create a model
    $model = TestModel::create([
        'name' => 'Test Model',
        'active' => true,
    ]);
    
    $modelId = $model->id;
    $hash = $model->getCurrentHash();
    expect($hash)->not->toBeNull();
    
    // Delete the model via Eloquent
    $model->delete();

    // The event should have been fired and handled by the listener

    // Assert publish record was created
    $publish = Publish::where('publisher_id', $publisher->id)
        ->whereNull('hash_id')
        ->first();

    expect($publish)->not->toBeNull();
    expect($publish->metadata['type'])->toBe('deletion');
    expect($publish->metadata['model_class'])->toBe(TestModel::class);
    expect($publish->metadata['model_id'])->toBe($modelId);

    // Assert job was dispatched
    Queue::assertPushed(DeletePublishJob::class);
});

it('does not trigger deletion publishers for regular publishers on delete', function () {
    // Create a regular publisher (not a delete publisher)
    $regularPublisher = Publisher::create([
        'name' => 'Regular Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => \ameax\HashChangeDetector\Publishers\LogPublisher::class,
        'status' => 'active',
    ]);

    // Create a model
    $model = TestModel::create([
        'name' => 'Test Model',
        'active' => false,
    ]);
    
    // Delete the model
    $model->delete();

    // Assert no deletion publish records were created
    $publishes = Publish::whereNull('hash_id')->get();
    expect($publishes)->toHaveCount(0);
});

it('handles soft deletes appropriately', function () {
    // This test would need a model with SoftDeletes trait
    // For now, we'll skip this as TestModel doesn't use SoftDeletes
    expect(true)->toBeTrue();
})->skip('TestModel does not use SoftDeletes');

it('triggers deletion publishers even when model has no dependents', function () {
    $publisher = Publisher::create([
        'name' => 'Delete Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogDeletePublisher::class,
        'status' => 'active',
    ]);

    // Create a standalone model with no relationships
    $model = TestModel::create([
        'name' => 'Standalone Model',
        'active' => true,
    ]);
    
    $modelId = $model->id;
    
    // Delete the model
    $model->delete();

    // Assert publish record was created
    $publish = Publish::where('publisher_id', $publisher->id)
        ->whereNull('hash_id')
        ->where('metadata->model_id', $modelId)
        ->first();

    expect($publish)->not->toBeNull();
    expect($publish->status)->toBe('pending');
});

it('provides last known hash data to deletion publishers', function () {
    $publisher = Publisher::create([
        'name' => 'Delete Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogDeletePublisher::class,
        'status' => 'active',
    ]);

    $model = TestModel::create([
        'name' => 'Model with Data',
        'active' => true,
    ]);
    
    $hash = $model->getCurrentHash();
    $attributeHash = $hash->attribute_hash;
    $compositeHash = $hash->composite_hash;
    
    $model->delete();

    $publish = Publish::where('publisher_id', $publisher->id)
        ->whereNull('hash_id')
        ->first();

    expect($publish->metadata['last_known_data']['attribute_hash'])->toBe($attributeHash);
    expect($publish->metadata['last_known_data']['composite_hash'])->toBe($compositeHash);
    expect($publish->metadata['last_known_data']['deleted_at'])->not->toBeNull();
});