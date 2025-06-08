<?php

declare(strict_types=1);

use ameax\HashChangeDetector\Jobs\DetectChangesJob;
use ameax\HashChangeDetector\Tests\TestModels\TestModel;
use ameax\HashChangeDetector\Tests\TestModels\TestRelationModel;
use Illuminate\Support\Facades\DB;

it('fixes the specific bug: parent notification works even when dependent hash is already current', function () {
    // Create parent and dependent
    $post = TestModel::create([
        'name' => 'Test Post',
        'active' => true,
    ]);

    $comment = TestRelationModel::create([
        'test_model_id' => $post->id,
        'key' => 'test_comment',
        'value' => 100,
        'order' => 1,
    ]);

    // Force post to include comment in its composite hash
    $post->refresh();
    $post->load('testRelations');
    $post->updateHash();

    $originalPostHash = $post->getCurrentHash()->composite_hash;

    // SCENARIO: Comment's hash is already up-to-date, but parent needs to recalculate
    // This simulates the case where DetectChangesJob runs on a comment that's already been processed

    // First, ensure comment hash is current
    $comment->updateHash();
    $commentHashBeforeDb = $comment->getCurrentHash()->attribute_hash;

    // Now change comment via direct DB (this should trigger parent update)
    DB::table('test_relation_models')
        ->where('id', $comment->id)
        ->update([
            'key' => 'modified_comment',
            'updated_at' => now(),
        ]);

    // Run detection - this will call updateHash() on comment
    // Even though comment hash might not change, parent should be notified
    $job = new DetectChangesJob(TestRelationModel::class);
    $job->handle();

    // Verify parent was updated
    $post->refresh();
    $newPostHash = $post->getCurrentHash()->composite_hash;

    expect($newPostHash)->not->toBe($originalPostHash, 'Parent hash should change even when dependent hash is already current');
});

it('verifies RelatedModelUpdated event always fires from updateHash', function () {
    $eventsFired = [];

    // Listen for RelatedModelUpdated events
    Event::listen(\ameax\HashChangeDetector\Events\RelatedModelUpdated::class, function ($event) use (&$eventsFired) {
        $eventsFired[] = [
            'model' => get_class($event->model),
            'id' => $event->model->getKey(),
            'action' => $event->action,
        ];
    });

    $comment = TestRelationModel::create([
        'test_model_id' => 1,
        'key' => 'test',
        'value' => 100,
        'order' => 1,
    ]);

    // Clear events from creation
    $eventsFired = [];

    // Call updateHash when hash hasn't changed - should still fire event
    $comment->updateHash();

    expect($eventsFired)->toHaveCount(1);
    expect($eventsFired[0]['action'])->toBe('updated');
    expect($eventsFired[0]['model'])->toBe(TestRelationModel::class);
});
