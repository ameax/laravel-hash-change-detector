<?php

use ameax\HashChangeDetector\Jobs\DetectChangesJob;
use ameax\HashChangeDetector\Models\Hash;
use ameax\HashChangeDetector\Events\HashableModelDeleted;
use ameax\HashChangeDetector\Tests\TestModels\TestModel;
use ameax\HashChangeDetector\Tests\TestModels\TestRelationModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

it('detects when a related model is deleted directly in database', function () {
    // Create parent and child models
    $parent = TestModel::create([
        'name' => 'Parent Model',
        'description' => 'Parent Description',
        'price' => 100.00,
        'active' => true,
    ]);

    $child = TestRelationModel::create([
        'test_model_id' => $parent->id,
        'key' => 'child-key',
        'value' => 'Child Value',
        'order' => 1,
    ]);

    // Get initial parent hash
    $initialParentHash = $parent->getCurrentHash()->composite_hash;

    // Delete child directly in database
    DB::table('test_relation_models')
        ->where('id', $child->id)
        ->delete();

    // Run detection job
    $job = new DetectChangesJob(TestRelationModel::class);
    $job->handle();

    // Parent hash should be updated
    $parent->refresh();
    $updatedParentHash = $parent->getCurrentHash()->composite_hash;
    
    expect($updatedParentHash)->not->toBe($initialParentHash);
    
    // Child hash should be deleted
    $childHash = Hash::where('hashable_type', TestRelationModel::class)
        ->where('hashable_id', $child->id)
        ->first();
    
    expect($childHash)->toBeNull();
});

it('fires event when a parent model is deleted directly in database', function () {
    Event::fake([HashableModelDeleted::class]);
    
    // Create parent model
    $parent = TestModel::create([
        'name' => 'Parent Model',
        'description' => 'Will be deleted',
        'price' => 100.00,
        'active' => true,
    ]);
    
    $parentId = $parent->id;
    $hash = $parent->getCurrentHash();

    // Delete parent directly in database
    DB::table('test_models')
        ->where('id', $parentId)
        ->delete();

    // Run detection job
    $job = new DetectChangesJob(TestModel::class);
    $job->handle();

    // Event should be fired
    Event::assertDispatched(HashableModelDeleted::class, function ($event) use ($parentId) {
        return $event->modelId === $parentId 
            && $event->modelClass === TestModel::class;
    });
    
    // Hash should be deleted
    $parentHash = Hash::where('hashable_type', TestModel::class)
        ->where('hashable_id', $parentId)
        ->first();
    
    expect($parentHash)->toBeNull();
});

it('handles cascade deletions correctly', function () {
    // Create parent with multiple children
    $parent = TestModel::create([
        'name' => 'Parent Model',
        'description' => 'Has children',
        'price' => 100.00,
        'active' => true,
    ]);

    $children = collect([
        TestRelationModel::create(['test_model_id' => $parent->id, 'key' => 'child-1', 'value' => 'Value 1', 'order' => 1]),
        TestRelationModel::create(['test_model_id' => $parent->id, 'key' => 'child-2', 'value' => 'Value 2', 'order' => 2]),
        TestRelationModel::create(['test_model_id' => $parent->id, 'key' => 'child-3', 'value' => 'Value 3', 'order' => 3]),
    ]);

    // Delete all children directly
    DB::table('test_relation_models')
        ->whereIn('id', $children->pluck('id'))
        ->delete();

    // Get parent's initial composite hash
    $initialCompositeHash = $parent->getCurrentHash()->composite_hash;
    
    // Run detection for children
    $job = new DetectChangesJob(TestRelationModel::class);
    $job->handle();

    // All child hashes should be deleted
    $remainingChildHashes = Hash::where('hashable_type', TestRelationModel::class)
        ->whereIn('hashable_id', $children->pluck('id'))
        ->count();
    
    expect($remainingChildHashes)->toBe(0);
    
    // Parent should still exist with updated hash
    $parent->refresh();
    $parentHash = $parent->getCurrentHash();
    expect($parentHash)->not->toBeNull();
    
    // Parent's composite hash should have changed
    expect($parentHash->composite_hash)->not->toBe($initialCompositeHash);
    
    // Check that parent has no more related models
    $parent->load('testRelations');
    expect($parent->testRelations)->toHaveCount(0);
});

it('cleans up pending publishes when parent model is deleted', function () {
    Event::fake([HashableModelDeleted::class]);
    
    // Create parent model
    $parent = TestModel::create([
        'name' => 'Parent Model',
        'description' => 'Will be deleted',
        'price' => 100.00,
        'active' => true,
    ]);
    
    $parentId = $parent->id;
    
    // Get parent hash ID
    $hashId = $parent->getCurrentHash()->id;
    
    // Create some pending publishes
    DB::table('publishes')->insert([
        [
            'hash_id' => $hashId,
            'publisher_id' => 1,
            'published_hash' => md5('test-hash-1'),
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'hash_id' => $hashId,
            'publisher_id' => 2,
            'published_hash' => md5('test-hash-2'),
            'status' => 'deferred',
            'attempts' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    // Delete parent directly in database
    DB::table('test_models')
        ->where('id', $parentId)
        ->delete();

    // Run detection job
    $job = new DetectChangesJob(TestModel::class);
    $job->handle();

    // Pending publishes should be deleted
    $remainingPublishes = DB::table('publishes')
        ->where('hash_id', $hashId)
        ->count();
    
    expect($remainingPublishes)->toBe(0);
});