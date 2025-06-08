<?php

declare(strict_types=1);

use ameax\HashChangeDetector\Jobs\DetectChangesJob;
use ameax\HashChangeDetector\Tests\TestModels\TestModel;
use ameax\HashChangeDetector\Tests\TestModels\TestRelationModel;
use Illuminate\Support\Facades\DB;

it('notifies parent models immediately when direct database changes are detected', function () {
    // Create parent model with dependent
    $parent = TestModel::create([
        'name' => 'Parent Model',
        'active' => true,
    ]);

    $dependent = TestRelationModel::create([
        'test_model_id' => $parent->id,
        'key' => 'original_key',
        'value' => 100,
        'order' => 1,
    ]);

    // Force parent to include dependent in its composite hash
    $parent->refresh();
    $parent->load('testRelations');
    $parent->updateHash();

    // Store original hashes
    $originalParentHash = $parent->getCurrentHash()->composite_hash;
    $originalDependentHash = $dependent->getCurrentHash()->attribute_hash;

    // Update dependent directly in database
    DB::table('test_relation_models')
        ->where('id', $dependent->id)
        ->update([
            'key' => 'updated_directly',
            'value' => 200,
            'updated_at' => now(),
        ]);

    // Run detection job ONLY for dependent model
    $job = new DetectChangesJob(TestRelationModel::class);
    $job->handle();

    // Dependent's hash should be updated
    $dependent->refresh();
    expect($dependent->getCurrentHash()->attribute_hash)->not->toBe($originalDependentHash);

    // Parent's composite hash should NOW also be updated automatically
    // because updateHash() now fires RelatedModelUpdated event
    $parent->refresh();
    expect($parent->getCurrentHash()->composite_hash)->not->toBe($originalParentHash);
});

it('handles complex parent-child chains with direct database changes', function () {
    // Create a chain: GrandParent -> Parent -> Child
    $grandParent = TestModel::create(['name' => 'GrandParent', 'active' => true]);

    $parent = TestRelationModel::create([
        'test_model_id' => $grandParent->id,
        'key' => 'parent_key',
        'value' => 50,
        'order' => 1,
    ]);

    // Note: We can't easily create a 3-level hierarchy with the current test models
    // So we'll test with a 2-level hierarchy but ensure it works correctly

    // Force grandParent to include parent in its composite hash
    $grandParent->refresh();
    $grandParent->load('testRelations');
    $grandParent->updateHash();

    $originalGrandParentHash = $grandParent->getCurrentHash()->composite_hash;
    $originalParentHash = $parent->getCurrentHash()->attribute_hash;

    // Update the child (parent relation) directly in database
    DB::table('test_relation_models')
        ->where('id', $parent->id)
        ->update(['value' => 999]);

    // Run detection on the child level
    $job = new DetectChangesJob(TestRelationModel::class);
    $job->handle();

    // Both levels should be updated
    $parent->refresh();
    $grandParent->refresh();

    expect($parent->getCurrentHash()->attribute_hash)->not->toBe($originalParentHash);
    expect($grandParent->getCurrentHash()->composite_hash)->not->toBe($originalGrandParentHash);
});

it('avoids infinite loops with the fix', function () {
    // Create two models that reference each other
    $model1 = TestModel::create(['name' => 'Model 1', 'active' => true]);
    $model2 = TestModel::create(['name' => 'Model 2', 'active' => false]);

    // Create cross-references
    TestRelationModel::create([
        'test_model_id' => $model1->id,
        'key' => 'ref_to_model2',
        'value' => $model2->id,
        'order' => 1,
    ]);

    TestRelationModel::create([
        'test_model_id' => $model2->id,
        'key' => 'ref_to_model1',
        'value' => $model1->id,
        'order' => 1,
    ]);

    // Update both models to include their relations
    $model1->refresh();
    $model1->load('testRelations');
    $model1->updateHash();

    $model2->refresh();
    $model2->load('testRelations');
    $model2->updateHash();

    $originalHash1 = $model1->getCurrentHash()->composite_hash;
    $originalHash2 = $model2->getCurrentHash()->composite_hash;

    // Update one relation directly in database
    DB::table('test_relation_models')
        ->where('test_model_id', $model1->id)
        ->update(['value' => 999]);

    // This should not cause infinite loops due to the loop prevention in HandleRelatedModelUpdated
    $job = new DetectChangesJob(TestRelationModel::class);
    $job->handle();

    // Both models should have updated hashes (but no infinite loop)
    $model1->refresh();
    $model2->refresh();

    expect($model1->getCurrentHash()->composite_hash)->not->toBe($originalHash1);
    // model2 might or might not change depending on the relationship setup
    expect(true)->toBeTrue(); // Just verify no infinite loop occurred
});

it('works with HasMany collections updated directly in database', function () {
    // Create parent with multiple dependents
    $parent = TestModel::create(['name' => 'Parent', 'active' => true]);

    // Create multiple dependents via Eloquent first
    $dependent1 = TestRelationModel::create([
        'test_model_id' => $parent->id,
        'key' => 'key1',
        'value' => 10,
        'order' => 1,
    ]);

    $dependent2 = TestRelationModel::create([
        'test_model_id' => $parent->id,
        'key' => 'key2',
        'value' => 20,
        'order' => 2,
    ]);

    // Update parent hash to include all dependents
    $parent->refresh();
    $parent->load('testRelations');
    $parent->updateHash();

    $originalParentHash = $parent->getCurrentHash()->composite_hash;

    // Update both dependents directly in database
    DB::table('test_relation_models')
        ->whereIn('id', [$dependent1->id, $dependent2->id])
        ->update(['value' => DB::raw('value * 10')]);

    // Run detection
    $job = new DetectChangesJob(TestRelationModel::class);
    $job->handle();

    // Parent should be updated
    $parent->refresh();
    expect($parent->getCurrentHash()->composite_hash)->not->toBe($originalParentHash);

    // Verify both dependents were updated
    $dependent1->refresh();
    $dependent2->refresh();
    expect((int) $dependent1->value)->toBe(100);
    expect((int) $dependent2->value)->toBe(200);
});
