<?php

declare(strict_types=1);

use ameax\HashChangeDetector\Tests\TestModels\TestCountryModel;
use ameax\HashChangeDetector\Tests\TestModels\TestPostModel;
use ameax\HashChangeDetector\Tests\TestModels\TestUserModel;

beforeEach(function () {
    // Create a default country for tests
    $this->country = TestCountryModel::create([
        'name' => 'Test Country',
        'code' => 'TC',
    ]);
});

it('notifies all posts when user changes', function () {
    // Create a user with multiple posts
    $user = TestUserModel::create([
        'country_id' => $this->country->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $post1 = TestPostModel::create([
        'user_id' => $user->id,
        'title' => 'First Post',
        'content' => 'Content 1',
        'published' => true,
    ]);

    $post2 = TestPostModel::create([
        'user_id' => $user->id,
        'title' => 'Second Post',
        'content' => 'Content 2',
        'published' => false,
    ]);

    $post3 = TestPostModel::create([
        'user_id' => $user->id,
        'title' => 'Third Post',
        'content' => 'Content 3',
        'published' => true,
    ]);

    // Force posts to calculate composite hash with user included
    $post1->load('user');
    $post2->load('user');
    $post3->load('user');
    $post1->updateHash();
    $post2->updateHash();
    $post3->updateHash();

    // Store original hashes and user hash
    $originalHash1 = $post1->getCurrentHash()->composite_hash;
    $originalHash2 = $post2->getCurrentHash()->composite_hash;
    $originalHash3 = $post3->getCurrentHash()->composite_hash;
    $originalUserHash = $user->getCurrentHash()->attribute_hash;

    // Update the user
    $user->update(['name' => 'Jane Doe']);

    // Check that user's hash changed
    $user->refresh();
    $newUserHash = $user->getCurrentHash()->attribute_hash;
    expect($newUserHash)->not->toBe($originalUserHash);

    // Refresh posts and check their hashes have been updated
    $post1->refresh();
    $post2->refresh();
    $post3->refresh();

    expect($post1->getCurrentHash()->composite_hash)->not->toBe($originalHash1);
    expect($post2->getCurrentHash()->composite_hash)->not->toBe($originalHash2);
    expect($post3->getCurrentHash()->composite_hash)->not->toBe($originalHash3);
});

it('stores multiple dependent references in hash_dependents table', function () {
    $user = TestUserModel::create([
        'country_id' => $this->country->id,
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    $post1 = TestPostModel::create([
        'user_id' => $user->id,
        'title' => 'Post 1',
        'content' => 'Content',
        'published' => true,
    ]);

    $post2 = TestPostModel::create([
        'user_id' => $user->id,
        'title' => 'Post 2',
        'content' => 'Content',
        'published' => true,
    ]);

    // Check that user's hash has multiple dependents
    $userHash = $user->getCurrentHash();
    $dependents = $userHash->dependents;

    expect($dependents)->toHaveCount(2);

    $dependentIds = $dependents->pluck('dependent_model_id')->sort()->values()->toArray();
    expect($dependentIds)->toBe([$post1->id, $post2->id]);

    // All should have the same relation name
    expect($dependents->pluck('relation_name')->unique()->toArray())->toBe(['posts']);
});

it('handles empty collections correctly', function () {
    $user = TestUserModel::create([
        'country_id' => $this->country->id,
        'name' => 'User Without Posts',
        'email' => 'nopost@example.com',
    ]);

    // User has no posts, but the relation should not cause errors
    $userHash = $user->getCurrentHash();

    expect($userHash->dependents)->toHaveCount(0);

    // Updating user should work fine
    $user->update(['name' => 'Updated Name']);

    expect($user->fresh()->name)->toBe('Updated Name');
});
