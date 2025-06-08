<?php

declare(strict_types=1);

use ameax\HashChangeDetector\Jobs\DetectChangesJob;
use ameax\HashChangeDetector\Tests\TestModels\TestModel;
use ameax\HashChangeDetector\Tests\TestModels\TestRelationModel;
use Illuminate\Support\Facades\DB;

it('updates parent hash when dependent model is created directly in database', function () {
    // Create parent model
    $parent = TestModel::create([
        'name' => 'Parent Model',
        'active' => true,
    ]);
    
    // Store original composite hash
    $originalHash = $parent->getCurrentHash()->composite_hash;
    
    // Create dependent model directly in database (bypassing Eloquent)
    DB::table('test_relation_models')->insert([
        'test_model_id' => $parent->id,
        'key' => 'direct_db_key',
        'value' => 100,
        'order' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    // Run detection job for the dependent model class
    $job = new DetectChangesJob(TestRelationModel::class);
    $job->handle();
    
    // Parent hash should now be updated immediately because updateHash() fires RelatedModelUpdated
    $parent->refresh();
    $updatedHash = $parent->getCurrentHash()->composite_hash;
    expect($updatedHash)->not->toBe($originalHash);
    
    // Verify the relation was properly detected
    $relations = TestRelationModel::where('test_model_id', $parent->id)->get();
    expect($relations)->toHaveCount(1);
    expect($relations->first()->key)->toBe('direct_db_key');
});

it('updates parent hash when dependent model is changed directly in database', function () {
    // Create parent model with a dependent
    $parent = TestModel::create([
        'name' => 'Parent Model',
        'active' => true,
    ]);
    
    $dependent = TestRelationModel::create([
        'test_model_id' => $parent->id,
        'key' => 'original_key',
        'value' => 50,
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
            'key' => 'updated_key',
            'value' => 200,
            'updated_at' => now(),
        ]);
    
    // Run detection job for dependent model
    $job = new DetectChangesJob(TestRelationModel::class);
    $job->handle();
    
    // Dependent's hash should be updated
    $dependent->refresh();
    expect($dependent->getCurrentHash()->attribute_hash)->not->toBe($originalDependentHash);
    
    // Parent's composite hash should now be updated immediately because
    // updateHash() now fires RelatedModelUpdated events
    $parent->refresh();
    expect($parent->getCurrentHash()->composite_hash)->not->toBe($originalParentHash);
});

it('handles bulk creation of dependents directly in database', function () {
    // Create multiple parent models
    $parent1 = TestModel::create(['name' => 'Parent 1', 'active' => true]);
    $parent2 = TestModel::create(['name' => 'Parent 2', 'active' => false]);
    
    $originalHash1 = $parent1->getCurrentHash()->composite_hash;
    $originalHash2 = $parent2->getCurrentHash()->composite_hash;
    
    // Bulk insert dependents directly
    DB::table('test_relation_models')->insert([
        [
            'test_model_id' => $parent1->id,
            'key' => 'bulk_key_1',
            'value' => 10,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'test_model_id' => $parent1->id,
            'key' => 'bulk_key_2',
            'value' => 20,
            'order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'test_model_id' => $parent2->id,
            'key' => 'bulk_key_3',
            'value' => 30,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);
    
    // Run detection for dependents
    $job = new DetectChangesJob(TestRelationModel::class);
    $job->handle();
    
    // Run detection for parents to update composite hashes
    $parentJob = new DetectChangesJob(TestModel::class);
    $parentJob->handle();
    
    // Both parents should have updated composite hashes
    $parent1->refresh();
    $parent2->refresh();
    
    expect($parent1->getCurrentHash()->composite_hash)->not->toBe($originalHash1);
    expect($parent2->getCurrentHash()->composite_hash)->not->toBe($originalHash2);
    
    // Verify correct number of relations
    expect($parent1->testRelations()->count())->toBe(2);
    expect($parent2->testRelations()->count())->toBe(1);
});

it('handles mixed database operations on dependents', function () {
    $parent = TestModel::create(['name' => 'Parent', 'active' => true]);
    
    // Create one dependent via Eloquent
    $dependent1 = TestRelationModel::create([
        'test_model_id' => $parent->id,
        'key' => 'eloquent_key',
        'value' => 100,
        'order' => 1,
    ]);
    
    $parent->refresh();
    $parent->updateHash();
    $hashAfterEloquent = $parent->getCurrentHash()->composite_hash;
    
    // Create another directly in DB
    DB::table('test_relation_models')->insert([
        'test_model_id' => $parent->id,
        'key' => 'db_created_key',
        'value' => 200,
        'order' => 2,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    // Update the first one directly in DB
    DB::table('test_relation_models')
        ->where('id', $dependent1->id)
        ->update(['value' => 150]);
    
    // Run detection
    $job = new DetectChangesJob(TestRelationModel::class);
    $job->handle();
    
    $parentJob = new DetectChangesJob(TestModel::class);
    $parentJob->handle();
    
    // Parent hash should be different from after Eloquent creation
    $parent->refresh();
    expect($parent->getCurrentHash()->composite_hash)->not->toBe($hashAfterEloquent);
    
    // Verify we have 2 dependents
    expect($parent->testRelations()->count())->toBe(2);
});