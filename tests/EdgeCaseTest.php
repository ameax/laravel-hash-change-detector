<?php

use ameax\HashChangeDetector\Tests\TestModels\TestModel;
use ameax\HashChangeDetector\Tests\TestModels\TestRelationModel;
use Illuminate\Support\Str;

it('handles very large attribute values correctly', function () {
    $largeString = Str::random(10000); // 10KB string
    
    $model = TestModel::create([
        'name' => 'Large Value Test',
        'description' => $largeString,
        'price' => 99.99,
        'active' => true,
    ]);
    
    $hash = $model->getCurrentHash();
    
    expect($hash)->not->toBeNull();
    expect($hash->attribute_hash)->toBe(md5('1|' . $largeString . '|Large Value Test|99.99'));
});

it('handles models with unicode and special characters', function () {
    $model = TestModel::create([
        'name' => '日本語テスト 🚀',
        'description' => 'Special chars: <>&"\'',
        'price' => 99.99,
        'active' => true,
    ]);
    
    $hash = $model->getCurrentHash();
    
    expect($hash)->not->toBeNull();
    // Hash should handle unicode correctly
    expect($hash->attribute_hash)->toBe(md5('1|Special chars: <>&"\'|日本語テスト 🚀|99.99'));
});

it('handles concurrent hash updates gracefully', function () {
    $model = TestModel::create([
        'name' => 'Concurrent Test',
        'description' => 'Test',
        'price' => 100,
        'active' => true,
    ]);
    
    // Simulate concurrent updates
    $promises = [];
    for ($i = 0; $i < 5; $i++) {
        $promises[] = function () use ($model, $i) {
            $model->update(['price' => 100 + $i]);
        };
    }
    
    // Execute all updates
    foreach ($promises as $promise) {
        $promise();
    }
    
    // Should have final hash without errors
    $model->refresh();
    $hash = $model->getCurrentHash();
    expect($hash)->not->toBeNull();
    expect($hash->attribute_hash)->toBe(md5('1|Test|Concurrent Test|104.00'));
});

it('handles multiple related models with consistent ordering', function () {
    $parent = TestModel::create([
        'name' => 'Parent Model',
        'description' => 'Test',
        'price' => 100,
        'active' => true,
    ]);

    // Create multiple children
    $children = [];
    for ($i = 1; $i <= 3; $i++) {
        $children[] = TestRelationModel::create([
            'test_model_id' => $parent->id,
            'value' => "Child $i",
            'key' => "key$i",
        ]);
    }

    $parent->load('testRelations');
    $hash1 = $parent->calculateCompositeHash();

    // Reload in different order
    $parent->load(['testRelations' => function ($query) {
        $query->orderBy('id', 'desc');
    }]);
    
    $hash2 = $parent->calculateCompositeHash();

    // Hashes should be identical regardless of load order
    expect($hash1)->toBe($hash2);
});

it('handles empty hashable attributes gracefully', function () {
    $model = TestModel::create([
        'name' => '',
        'description' => '',
        'price' => 0,
        'active' => false,
    ]);
    
    $hash = $model->getCurrentHash();
    
    expect($hash)->not->toBeNull();
    // Decimals are stored as strings with .00
    expect($hash->attribute_hash)->toBe(md5('0|||0.00'));
});

it('handles null model relationships', function () {
    $model = TestModel::create([
        'name' => 'No Relations',
        'description' => 'Test',
        'price' => 100,
        'active' => true,
    ]);
    
    // Model has no relations
    $hash = $model->getCurrentHash();
    
    // When model has relations defined but none exist, composite hash includes empty relation hashes
    // So it won't equal attribute hash
    expect($hash->composite_hash)->not->toBeNull();
    expect($hash->attribute_hash)->not->toBeNull();
});