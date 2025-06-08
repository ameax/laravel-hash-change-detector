<?php

declare(strict_types=1);

use ameax\HashChangeDetector\Listeners\HandleRelatedModelUpdated;
use ameax\HashChangeDetector\Tests\TestModels\TestCountryModel;
use ameax\HashChangeDetector\Tests\TestModels\TestPostModel;
use ameax\HashChangeDetector\Tests\TestModels\TestUserModel;

beforeEach(function () {
    // Clear the processing stack before each test
    HandleRelatedModelUpdated::clearProcessingStack();

    // Create a default country for tests
    $this->country = TestCountryModel::create([
        'name' => 'Test Country',
        'code' => 'TC',
    ]);
});

it('prevents infinite loops in bidirectional relationships', function () {
    // Create models with potential circular dependency
    // User notifies posts, Post notifies user
    $user = TestUserModel::create([
        'country_id' => $this->country->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $post = TestPostModel::create([
        'user_id' => $user->id,
        'title' => 'Test Post',
        'content' => 'Content',
        'published' => true,
    ]);

    // Store original hashes
    $originalUserHash = $user->getCurrentHash()->attribute_hash;
    $originalPostHash = $post->getCurrentHash()->composite_hash;

    // This should not cause an infinite loop
    // User update -> notifies posts -> posts update -> would notify user (but prevented)
    $user->update(['name' => 'Jane Doe']);

    // Verify the update happened
    expect($user->fresh()->name)->toBe('Jane Doe');

    // Verify hashes were updated appropriately
    $user->refresh();
    $post->refresh();

    expect($user->getCurrentHash()->attribute_hash)->not->toBe($originalUserHash);
    expect($post->getCurrentHash()->composite_hash)->not->toBe($originalPostHash);
});

it('allows the same model to be updated in different update chains', function () {
    // Create a user with two posts
    $user = TestUserModel::create([
        'country_id' => $this->country->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $post1 = TestPostModel::create([
        'user_id' => $user->id,
        'title' => 'Post 1',
        'content' => 'Content 1',
        'published' => true,
    ]);

    $post2 = TestPostModel::create([
        'user_id' => $user->id,
        'title' => 'Post 2',
        'content' => 'Content 2',
        'published' => true,
    ]);

    // Update user (should update both posts)
    $user->update(['name' => 'Jane Doe']);

    // Now update post1 directly (should update user, but not cause infinite loop)
    $post1->update(['title' => 'Updated Post 1']);

    // Verify all updates happened
    expect($user->fresh()->name)->toBe('Jane Doe');
    expect($post1->fresh()->title)->toBe('Updated Post 1');
    expect($post2->fresh()->title)->toBe('Post 2'); // Unchanged
});

it('handles complex nested relationships without loops', function () {
    // Create a more complex scenario
    $user1 = TestUserModel::create([
        'country_id' => $this->country->id,
        'name' => 'User 1',
        'email' => 'user1@example.com',
    ]);

    $user2 = TestUserModel::create([
        'country_id' => $this->country->id,
        'name' => 'User 2',
        'email' => 'user2@example.com',
    ]);

    // Create posts that reference each other's users
    $post1 = TestPostModel::create([
        'user_id' => $user1->id,
        'title' => 'Post by User 1',
        'content' => 'Content',
        'published' => true,
    ]);

    $post2 = TestPostModel::create([
        'user_id' => $user2->id,
        'title' => 'Post by User 2',
        'content' => 'Content',
        'published' => true,
    ]);

    // Update country (should update users -> posts)
    $this->country->update(['name' => 'Updated Country']);

    // Verify updates cascaded properly
    $user1->refresh();
    $user2->refresh();
    $post1->refresh();
    $post2->refresh();

    // All should have updated hashes due to cascade
    expect($this->country->fresh()->name)->toBe('Updated Country');
});

it('prevents loops even with self-referential relationships', function () {
    // Create a scenario where a model could potentially update itself
    $user = TestUserModel::create([
        'country_id' => $this->country->id,
        'name' => 'Self Referential User',
        'email' => 'self@example.com',
    ]);

    // Create a post that belongs to the user
    $post = TestPostModel::create([
        'user_id' => $user->id,
        'title' => 'Self Referential Post',
        'content' => 'Content',
        'published' => true,
    ]);

    // This should not cause infinite recursion
    // Post update -> notifies user -> user notifies posts (including this one) -> prevented
    $post->update(['title' => 'Updated Title']);

    expect($post->fresh()->title)->toBe('Updated Title');
});
