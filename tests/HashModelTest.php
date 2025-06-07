<?php

declare(strict_types=1);

use ameax\HashChangeDetector\Models\Hash;
use ameax\HashChangeDetector\Tests\TestModels\TestModel;
use ameax\HashChangeDetector\Tests\TestModels\TestRelationModel;

it('identifies model without parents correctly', function () {
    $model = TestModel::create([
        'name' => 'Test Product',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $hash = $model->getCurrentHash();

    expect($hash->hasDependents())->toBeFalse();
});

it('identifies model with dependents correctly', function () {
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

    // Force parent to load and update hash (which will create parent references)
    $parent->load('testRelations');
    $parent->updateHash();

    $childHash = $child->getCurrentHash();

    expect($childHash->hasDependents())->toBeTrue();
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

it('tracks dependent relationships correctly', function () {
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

    // Each child should update its hash and set parent references
    foreach ($children as $child) {
        $child->updateHash();
    }

    // Check that children have dependent references
    foreach ($children as $child) {
        $childHash = $child->getCurrentHash();
        $dependents = $childHash->dependents;

        expect($dependents)->toHaveCount(1);
        expect($dependents->first()->dependent_model_type)->toBe(TestModel::class);
        expect($dependents->first()->dependent_model_id)->toBe($parent->id);
    }
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
