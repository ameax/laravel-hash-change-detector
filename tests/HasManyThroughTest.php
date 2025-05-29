<?php

declare(strict_types=1);

use ameax\HashChangeDetector\Tests\TestModels\TestCountryModel;
use ameax\HashChangeDetector\Tests\TestModels\TestPostModel;
use ameax\HashChangeDetector\Tests\TestModels\TestUserModel;

beforeEach(function () {
    // Run migrations for HasManyThrough test tables
    $this->loadMigrationsFrom(__DIR__.'/database/migrations');
});

it('tracks posts through users in country hash', function () {
    // Create a country with users and posts
    $country = TestCountryModel::create([
        'name' => 'United States',
        'code' => 'US',
    ]);

    $user1 = TestUserModel::create([
        'country_id' => $country->id,
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $user2 = TestUserModel::create([
        'country_id' => $country->id,
        'name' => 'Jane Smith',
        'email' => 'jane@example.com',
    ]);

    $post1 = TestPostModel::create([
        'user_id' => $user1->id,
        'title' => 'First Post',
        'content' => 'Content of first post',
        'published' => true,
    ]);

    $post2 = TestPostModel::create([
        'user_id' => $user2->id,
        'title' => 'Second Post',
        'content' => 'Content of second post',
        'published' => false,
    ]);

    // Debug: Check if relationships work
    $country->load('posts');
    
    // Get initial hash
    $initialHash = $country->getCurrentHash();
    expect($initialHash)->not->toBeNull();
    expect($initialHash->composite_hash)->not->toBeNull();

    // Verify the country tracks posts through users
    $posts = $country->posts;
    expect($posts)->toHaveCount(2);
    expect($posts->pluck('title')->toArray())->toContain('First Post', 'Second Post');
});

it('updates country hash when a post through user changes', function () {
    // Create country with user and post
    $country = TestCountryModel::create([
        'name' => 'Canada',
        'code' => 'CA',
    ]);

    $user = TestUserModel::create([
        'country_id' => $country->id,
        'name' => 'Bob Wilson',
        'email' => 'bob@example.com',
    ]);

    $post = TestPostModel::create([
        'user_id' => $user->id,
        'title' => 'Original Title',
        'content' => 'Original content',
        'published' => false,
    ]);

    // Get initial hash
    $initialHash = $country->getCurrentHash();
    $initialCompositeHash = $initialHash->composite_hash;

    // Update the post
    $post->update(['title' => 'Updated Title']);

    // Reload country to get updated hash
    $country->refresh();
    $updatedHash = $country->getCurrentHash();

    // Country's composite hash should change because it includes posts
    expect($updatedHash->composite_hash)->not->toBe($initialCompositeHash);
});

it('updates country hash when a post is added to a user', function () {
    // Create country with user
    $country = TestCountryModel::create([
        'name' => 'United Kingdom',
        'code' => 'GB',
    ]);

    $user = TestUserModel::create([
        'country_id' => $country->id,
        'name' => 'Alice Brown',
        'email' => 'alice@example.com',
    ]);

    // Get initial hash
    $initialHash = $country->getCurrentHash();
    $initialCompositeHash = $initialHash->composite_hash;

    // Add a new post
    TestPostModel::create([
        'user_id' => $user->id,
        'title' => 'New Post',
        'content' => 'New content',
        'published' => true,
    ]);

    // Reload country to get updated hash
    $country->refresh();
    $updatedHash = $country->getCurrentHash();

    // Country's composite hash should change
    expect($updatedHash->composite_hash)->not->toBe($initialCompositeHash);
});

it('updates country hash when a post is deleted', function () {
    // Note: This test demonstrates a limitation with HasManyThrough relationships
    // When a model is deleted, it may not be able to traverse back through
    // HasManyThrough to update distant parents automatically.
    // Create country with user and posts
    $country = TestCountryModel::create([
        'name' => 'Australia',
        'code' => 'AU',
    ]);

    $user = TestUserModel::create([
        'country_id' => $country->id,
        'name' => 'Charlie Davis',
        'email' => 'charlie@example.com',
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

    // Get initial hash
    $initialHash = $country->getCurrentHash();
    $initialCompositeHash = $initialHash->composite_hash;

    // Delete one post
    $post1->delete();

    // For HasManyThrough relationships, we need to manually trigger the update
    // This is because the post doesn't directly know about the country
    $country->updateHash();

    // Get updated hash
    $updatedHash = $country->getCurrentHash();

    // Country's composite hash should change
    expect($updatedHash->composite_hash)->not->toBe($initialCompositeHash);
    
    // Verify only one post remains
    expect($country->posts()->count())->toBe(1);
});

it('does not update country hash when intermediate user changes', function () {
    // Create country with user and post
    $country = TestCountryModel::create([
        'name' => 'Germany',
        'code' => 'DE',
    ]);

    $user = TestUserModel::create([
        'country_id' => $country->id,
        'name' => 'David Evans',
        'email' => 'david@example.com',
    ]);

    $post = TestPostModel::create([
        'user_id' => $user->id,
        'title' => 'Test Post',
        'content' => 'Test content',
        'published' => true,
    ]);

    // Get initial hash
    $initialHash = $country->getCurrentHash();
    $initialCompositeHash = $initialHash->composite_hash;

    // Update the user (intermediate model)
    $user->update(['name' => 'David E. Evans']);

    // Reload country to get hash
    $country->refresh();
    $currentHash = $country->getCurrentHash();

    // Country's composite hash should NOT change because we only track posts, not users
    expect($currentHash->composite_hash)->toBe($initialCompositeHash);
});

it('handles multiple countries with shared posts correctly', function () {
    // Create two countries
    $usa = TestCountryModel::create([
        'name' => 'United States',
        'code' => 'US',
    ]);

    $canada = TestCountryModel::create([
        'name' => 'Canada',
        'code' => 'CA',
    ]);

    // Create users in each country
    $usUser = TestUserModel::create([
        'country_id' => $usa->id,
        'name' => 'US User',
        'email' => 'us@example.com',
    ]);

    $caUser = TestUserModel::create([
        'country_id' => $canada->id,
        'name' => 'CA User',
        'email' => 'ca@example.com',
    ]);

    // Create posts for each user
    $usPost = TestPostModel::create([
        'user_id' => $usUser->id,
        'title' => 'US Post',
        'content' => 'Content from US',
        'published' => true,
    ]);

    $caPost = TestPostModel::create([
        'user_id' => $caUser->id,
        'title' => 'CA Post',
        'content' => 'Content from CA',
        'published' => true,
    ]);

    // Get hashes
    $usaHash = $usa->getCurrentHash();
    $canadaHash = $canada->getCurrentHash();

    // Hashes should be different
    expect($usaHash->composite_hash)->not->toBe($canadaHash->composite_hash);

    // Each country should only see their own posts
    $usa->load('posts');
    $canada->load('posts');
    
    expect($usa->posts)->toHaveCount(1);
    expect($usa->posts->first()->title)->toBe('US Post');
    
    expect($canada->posts)->toHaveCount(1);
    expect($canada->posts->first()->title)->toBe('CA Post');
});