<?php

declare(strict_types=1);

use ameax\HashChangeDetector\Events\RelatedModelUpdated;
use ameax\HashChangeDetector\Tests\TestModels\TestCountryModel;
use ameax\HashChangeDetector\Tests\TestModels\TestPostModel;
use ameax\HashChangeDetector\Tests\TestModels\TestUserModel;

it('debug: checks dependent references after post creation', function () {
    $country = TestCountryModel::create([
        'name' => 'Test Country',
        'code' => 'TC',
    ]);

    $user = TestUserModel::create([
        'country_id' => $country->id,
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    // Check initial state - user has no dependents
    $userHash = $user->getCurrentHash();
    expect($userHash->dependents)->toHaveCount(0);

    // Create a post
    $post = TestPostModel::create([
        'user_id' => $user->id,
        'title' => 'Test Post',
        'content' => 'Content',
        'published' => true,
    ]);

    // Wait for event processing to complete
    // The post creation triggers user hash update via event

    // Refresh user and check dependents again
    $user->refresh();
    $userHash = $user->getCurrentHash();

    expect($userHash->dependents)->toHaveCount(1);
});

it('debug: manually triggers the event handler', function () {
    $country = TestCountryModel::create([
        'name' => 'Test Country',
        'code' => 'TC',
    ]);

    $user = TestUserModel::create([
        'country_id' => $country->id,
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    $post = TestPostModel::create([
        'user_id' => $user->id,
        'title' => 'Test Post',
        'content' => 'Content',
        'published' => true,
    ]);

    // Force the post to include user in composite hash
    $post->load('user');
    $post->updateHash();

    $originalComposite = $post->getCurrentHash()->composite_hash;
    $originalUserHash = $user->getCurrentHash()->attribute_hash;

    // Update user to change its hash
    $user->name = 'Updated User';
    $user->save();

    $newUserHash = $user->getCurrentHash()->attribute_hash;

    // Manually create and handle the event
    $event = new RelatedModelUpdated($user, 'updated');
    $handler = new \ameax\HashChangeDetector\Listeners\HandleRelatedModelUpdated;
    $handler->handle($event);

    // Check if post hash was updated
    $post->refresh();
    $newComposite = $post->getCurrentHash()->composite_hash;

    expect($newComposite)->not->toBe($originalComposite);
});
