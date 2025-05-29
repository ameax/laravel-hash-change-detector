<?php

use ameax\HashChangeDetector\Events\HashChanged;
use ameax\HashChangeDetector\Models\Hash;
use ameax\HashChangeDetector\Tests\TestModels\TestModel;
use ameax\HashChangeDetector\Tests\TestModels\TestRelationModel;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([HashChanged::class]);
});

it('creates hash when model is created', function () {
    $model = TestModel::create([
        'name' => 'Test Product',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    // Check hash record was created
    $hash = Hash::where('hashable_type', TestModel::class)
        ->where('hashable_id', $model->id)
        ->first();

    expect($hash)->not->toBeNull();
    // Account for decimal casting to string
    // Attributes are sorted alphabetically: active, description, name, price
    expect($hash->attribute_hash)->toBe(md5('1|Test Description|Test Product|99.99'));
    expect($hash->composite_hash)->toBe(md5($hash->attribute_hash));
    expect($hash->main_model_type)->toBeNull();
    expect($hash->main_model_id)->toBeNull();

    // Check event was fired
    Event::assertDispatched(HashChanged::class, function ($event) use ($model, $hash) {
        return $event->model->is($model) &&
               $event->attributeHash === $hash->attribute_hash &&
               $event->compositeHash === $hash->composite_hash;
    });
});

it('updates hash when model attributes change', function () {
    $model = TestModel::create([
        'name' => 'Original Name',
        'description' => 'Original Description',
        'price' => 50.00,
        'active' => true,
    ]);

    $originalHash = $model->getCurrentHash();
    Event::fake([HashChanged::class]); // Reset event fake

    // Update the model
    $model->update(['name' => 'Updated Name']);

    $newHash = $model->fresh()->getCurrentHash();

    expect($newHash->attribute_hash)->not->toBe($originalHash->attribute_hash);
    // Attributes are sorted alphabetically: active, description, name, price
    expect($newHash->attribute_hash)->toBe(md5('1|Original Description|Updated Name|50.00'));

    Event::assertDispatched(HashChanged::class);
});

it('does not update hash when non-hashable attributes change', function () {
    $model = TestModel::create([
        'name' => 'Test Product',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $originalHash = $model->getCurrentHash();
    Event::fake([HashChanged::class]); // Reset event fake

    // Update non-hashable attribute (updated_at)
    $model->touch();

    $newHash = $model->fresh()->getCurrentHash();

    expect($newHash->attribute_hash)->toBe($originalHash->attribute_hash);
    expect($newHash->updated_at->timestamp)->toBe($originalHash->updated_at->timestamp);

    Event::assertNotDispatched(HashChanged::class);
});

it('handles null values in hashable attributes', function () {
    $model = TestModel::create([
        'name' => 'Test Product',
        'description' => null,
        'price' => 0,
        'active' => false,
    ]);

    $hash = $model->getCurrentHash();

    // null becomes empty string, false becomes '0'
    // Attributes are sorted alphabetically: active, description, name, price
    expect($hash->attribute_hash)->toBe(md5('0||Test Product|0.00'));
});

it('creates hash for related models', function () {
    $model = TestModel::create([
        'name' => 'Main Model',
        'description' => 'Description',
        'price' => 100,
        'active' => true,
    ]);

    $relation = TestRelationModel::create([
        'test_model_id' => $model->id,
        'key' => 'color',
        'value' => 'red',
        'order' => 1,
    ]);

    $relationHash = $relation->getCurrentHash();

    expect($relationHash)->not->toBeNull();
    // Attributes are sorted alphabetically: key, order, value
    expect($relationHash->attribute_hash)->toBe(md5('color|1|red'));
    expect($relationHash->composite_hash)->toBe(md5($relationHash->attribute_hash));
});

it('updates parent composite hash when related model is added', function () {
    $model = TestModel::create([
        'name' => 'Main Model',
        'description' => 'Description',
        'price' => 100,
        'active' => true,
    ]);

    $originalHash = $model->getCurrentHash();
    Event::fake([HashChanged::class]); // Reset event fake

    // Add related model
    $relation = TestRelationModel::create([
        'test_model_id' => $model->id,
        'key' => 'size',
        'value' => 'large',
        'order' => 1,
    ]);

    // Force reload of relations
    $model->load('testRelations');
    $model->updateHash();

    $model->refresh();
    $newHash = $model->getCurrentHash();

    expect($newHash->attribute_hash)->toBe($originalHash->attribute_hash);
    expect($newHash->composite_hash)->not->toBe($originalHash->composite_hash);

    // Composite hash should include both model hashes (sorted)
    $hashes = [
        $newHash->attribute_hash,
        $relation->getCurrentHash()->attribute_hash
    ];
    sort($hashes);
    $expectedComposite = md5(implode('|', $hashes));
    expect($newHash->composite_hash)->toBe($expectedComposite);
});

it('updates parent composite hash when related model is updated', function () {
    $model = TestModel::create([
        'name' => 'Main Model',
        'description' => 'Description',
        'price' => 100,
        'active' => true,
    ]);

    $relation = TestRelationModel::create([
        'test_model_id' => $model->id,
        'key' => 'color',
        'value' => 'blue',
        'order' => 1,
    ]);

    // Force initial composite hash calculation
    $model->load('testRelations');
    $model->updateHash();
    
    $model->refresh();
    $originalComposite = $model->getCurrentHash()->composite_hash;
    Event::fake([HashChanged::class]); // Reset event fake

    // Update related model
    $relation->update(['value' => 'green']);

    $model->refresh();
    $newHash = $model->getCurrentHash();

    expect($newHash->composite_hash)->not->toBe($originalComposite);
});

it('updates parent composite hash when related model is deleted', function () {
    $model = TestModel::create([
        'name' => 'Main Model',
        'description' => 'Description',
        'price' => 100,
        'active' => true,
    ]);

    $relation1 = TestRelationModel::create([
        'test_model_id' => $model->id,
        'key' => 'color',
        'value' => 'red',
        'order' => 1,
    ]);

    $relation2 = TestRelationModel::create([
        'test_model_id' => $model->id,
        'key' => 'size',
        'value' => 'large',
        'order' => 2,
    ]);

    // Force initial composite hash calculation with all relations
    $model->load('testRelations');
    $model->updateHash();
    
    $model->refresh();
    $originalComposite = $model->getCurrentHash()->composite_hash;
    Event::fake([HashChanged::class]); // Reset event fake

    // Delete one related model
    $relation1->delete();

    // Force refresh to reload relations
    $model = TestModel::find($model->id);
    $model->load('testRelations');
    $newHash = $model->getCurrentHash();

    expect($newHash->composite_hash)->not->toBe($originalComposite);
    
    // Should only include remaining relation (sorted)
    $hashes = [
        $newHash->attribute_hash,
        $relation2->getCurrentHash()->attribute_hash
    ];
    sort($hashes);
    $expectedComposite = md5(implode('|', $hashes));
    expect($newHash->composite_hash)->toBe($expectedComposite);
});

it('handles multiple related models in composite hash with consistent ordering', function () {
    $model = TestModel::create([
        'name' => 'Main Model',
        'description' => 'Description',
        'price' => 100,
        'active' => true,
    ]);

    // Create relations in random order
    $relation2 = TestRelationModel::create([
        'test_model_id' => $model->id,
        'key' => 'size',
        'value' => 'medium',
        'order' => 2,
    ]);

    $relation1 = TestRelationModel::create([
        'test_model_id' => $model->id,
        'key' => 'color',
        'value' => 'blue',
        'order' => 1,
    ]);

    $relation3 = TestRelationModel::create([
        'test_model_id' => $model->id,
        'key' => 'material',
        'value' => 'cotton',
        'order' => 3,
    ]);

    $model->refresh();
    $model->load('testRelations');
    $model->updateHash();
    
    $hash = $model->getCurrentHash();

    // Hashes should be sorted for consistent ordering
    $hashes = [
        $hash->attribute_hash,
        $relation1->getCurrentHash()->attribute_hash,
        $relation2->getCurrentHash()->attribute_hash,
        $relation3->getCurrentHash()->attribute_hash,
    ];
    sort($hashes);

    $expectedComposite = md5(implode('|', $hashes));
    expect($hash->composite_hash)->toBe($expectedComposite);
});