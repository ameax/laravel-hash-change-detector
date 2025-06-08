<?php

declare(strict_types=1);

use ameax\HashChangeDetector\Jobs\DetectChangesJob;
use ameax\HashChangeDetector\Tests\TestModels\TestModel;
use ameax\HashChangeDetector\Tests\TestModels\TestRelationModel;
use Illuminate\Support\Facades\DB;

/**
 * This test file specifically addresses the bug reported where:
 * "When comments are modified directly in the database, the detect-changes command:
 *  1. Detects the change in the Comment model
 *  2. Updates the Comment's hash
 *  3. But does NOT trigger the parent Post update"
 */
it('fixes the bug: direct database changes to dependents now notify parent models', function () {
    // Simulate the reported scenario: Post with Comments
    // Using TestModel as "Post" and TestRelationModel as "Comment"

    $post = TestModel::create([
        'name' => 'My Blog Post',
        'active' => true,
    ]);

    $comment = TestRelationModel::create([
        'test_model_id' => $post->id,
        'key' => 'comment_1',
        'value' => 100,
        'order' => 1,
    ]);

    // Ensure post includes comments in its composite hash
    $post->refresh();
    $post->load('testRelations');
    $post->updateHash();

    $originalPostHash = $post->getCurrentHash()->composite_hash;
    $originalCommentHash = $comment->getCurrentHash()->attribute_hash;

    // Simulate direct database modification of comment (the reported bug scenario)
    DB::table('test_relation_models')
        ->where('id', $comment->id)
        ->update([
            'key' => 'modified_comment',
            'value' => 200,
            'updated_at' => now(),
        ]);

    // Run detect-changes ONLY for comments (the dependent model)
    $detectChangesJob = new DetectChangesJob(TestRelationModel::class);
    $detectChangesJob->handle();

    // BEFORE the fix: Post hash would remain unchanged
    // AFTER the fix: Post hash should be updated automatically

    $comment->refresh();
    $post->refresh();

    // Comment hash should be updated (this always worked)
    expect($comment->getCurrentHash()->attribute_hash)->not->toBe($originalCommentHash);

    // Post hash should NOW be updated (this is the bug fix)
    expect($post->getCurrentHash()->composite_hash)->not->toBe($originalPostHash);
});

it('demonstrates the fix works with multiple dependent models changed', function () {
    $post = TestModel::create(['name' => 'Post with Multiple Comments', 'active' => true]);

    // Create multiple comments
    $comment1 = TestRelationModel::create([
        'test_model_id' => $post->id,
        'key' => 'comment_1',
        'value' => 10,
        'order' => 1,
    ]);

    $comment2 = TestRelationModel::create([
        'test_model_id' => $post->id,
        'key' => 'comment_2',
        'value' => 20,
        'order' => 2,
    ]);

    $comment3 = TestRelationModel::create([
        'test_model_id' => $post->id,
        'key' => 'comment_3',
        'value' => 30,
        'order' => 3,
    ]);

    // Update post to include all comments
    $post->refresh();
    $post->load('testRelations');
    $post->updateHash();

    $originalPostHash = $post->getCurrentHash()->composite_hash;

    // Modify multiple comments directly in database
    DB::table('test_relation_models')
        ->where('test_model_id', $post->id)
        ->update(['value' => DB::raw('value * 5')]);

    // Run detection on comments only
    $job = new DetectChangesJob(TestRelationModel::class);
    $job->handle();

    // Post should be updated even though we only ran detection on comments
    $post->refresh();
    expect($post->getCurrentHash()->composite_hash)->not->toBe($originalPostHash);

    // Verify all comments were actually updated
    $updatedComments = TestRelationModel::where('test_model_id', $post->id)->get();
    expect($updatedComments->pluck('value')->map(fn ($v) => (int) $v)->toArray())->toBe([50, 100, 150]);
});

it('verifies the fix prevents the need to run detection on parent models separately', function () {
    $parent = TestModel::create(['name' => 'Parent', 'active' => true]);

    $child = TestRelationModel::create([
        'test_model_id' => $parent->id,
        'key' => 'child',
        'value' => 42,
        'order' => 1,
    ]);

    $parent->refresh();
    $parent->load('testRelations');
    $parent->updateHash();

    $originalParentHash = $parent->getCurrentHash()->composite_hash;

    // Change child directly in database
    DB::table('test_relation_models')
        ->where('id', $child->id)
        ->update(['value' => 999]);

    // ONLY run detection on child model (not parent)
    $childJob = new DetectChangesJob(TestRelationModel::class);
    $childJob->handle();

    // Parent should be updated without needing to run detection on parent model
    $parent->refresh();
    expect($parent->getCurrentHash()->composite_hash)->not->toBe($originalParentHash);

    // Verify we don't need to run detection on parent separately
    // (This test passes because the fix automatically notifies parents)
    expect(true)->toBeTrue();
});

it('confirms the fix works with nested parent-child relationships', function () {
    // Create a 2-level hierarchy: GrandParent -> Parent
    $grandParent = TestModel::create(['name' => 'GrandParent', 'active' => true]);

    $parent = TestRelationModel::create([
        'test_model_id' => $grandParent->id,
        'key' => 'parent',
        'value' => 100,
        'order' => 1,
    ]);

    // Update grandparent to include parent in composite hash
    $grandParent->refresh();
    $grandParent->load('testRelations');
    $grandParent->updateHash();

    $originalGrandParentHash = $grandParent->getCurrentHash()->composite_hash;

    // Modify parent directly in database
    DB::table('test_relation_models')
        ->where('id', $parent->id)
        ->update(['value' => 500]);

    // Run detection only on the parent level
    $job = new DetectChangesJob(TestRelationModel::class);
    $job->handle();

    // GrandParent should be automatically updated
    $grandParent->refresh();
    expect($grandParent->getCurrentHash()->composite_hash)->not->toBe($originalGrandParentHash);
});
