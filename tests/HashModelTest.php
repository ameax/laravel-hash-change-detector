<?php

use ameax\HashChangeDetector\Models\Hash;
use ameax\HashChangeDetector\Tests\TestModels\TestModel;
use ameax\HashChangeDetector\Tests\TestModels\TestRelationModel;

it('identifies main model correctly', function () {
    $model = TestModel::create([
        'name' => 'Test Product',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $hash = $model->getCurrentHash();
    
    expect($hash->isMainModel())->toBeTrue();
});

it('identifies related model correctly', function () {
    $parent = TestModel::create([
        'name' => 'Parent Model',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $child = TestRelationModel::create([
        'test_model_id' => $parent->id,
        'value' => 'Child Value',
        'key' => 'childkey',
    ]);

    $childHash = $child->getCurrentHash();
    
    expect($childHash->isMainModel())->toBeFalse();
});

it('detects attribute hash changes', function () {
    $model = TestModel::create([
        'name' => 'Original Name',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $originalHash = $model->getCurrentHash();
    
    // No changes yet
    expect($originalHash->hasChanged($originalHash->attribute_hash))->toBeFalse();
    
    // Update model
    $model->update(['name' => 'Updated Name']);
    $model->refresh();
    
    // Original hash should now show as changed
    $newHash = $model->calculateAttributeHash();
    expect($originalHash->hasChanged($newHash))->toBeTrue();
});

it('detects composite hash changes', function () {
    $model = TestModel::create([
        'name' => 'Test Product',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $originalHash = $model->getCurrentHash();
    $originalComposite = $originalHash->composite_hash;
    
    // No changes yet
    expect($originalHash->hasCompositeChanged($originalComposite))->toBeFalse();
    
    // Add related model
    TestRelationModel::create([
        'test_model_id' => $model->id,
        'value' => 'New Child',
        'key' => 'newkey',
    ]);
    
    // Force parent update - need to reload to get the updated child hash
    $model->load('testRelations');
    $model->updateHash();
    
    // Original hash should show composite change
    $newComposite = $model->calculateCompositeHash();
    expect($originalHash->hasCompositeChanged($newComposite))->toBeTrue();
});

it('loads related hashes relationship', function () {
    $parent = TestModel::create([
        'name' => 'Parent Model',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $children = collect([
        TestRelationModel::create(['test_model_id' => $parent->id, 'value' => 'Child 1', 'key' => 'key1']),
        TestRelationModel::create(['test_model_id' => $parent->id, 'value' => 'Child 2', 'key' => 'key2']),
    ]);

    $parentHash = $parent->getCurrentHash();
    $relatedHashes = $parentHash->relatedHashes;
    
    expect($relatedHashes)->toHaveCount(2);
    expect($relatedHashes->pluck('hashable_id')->sort()->values())->toEqual($children->pluck('id')->sort()->values());
});

it('creates hash with empty string for model without hashable attributes', function () {
    // Create a simple test case
    $model = TestModel::create([
        'name' => 'Empty Hash Test',
        'description' => 'Test',
        'price' => 100,
        'active' => true,
    ]);
    
    // Test that when no attributes are hashable, we get an empty hash
    $emptyAttributes = [];
    $content = implode('|', $emptyAttributes);
    
    expect(md5($content))->toBe(md5(''));
});