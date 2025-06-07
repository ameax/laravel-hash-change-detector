<?php

declare(strict_types=1);

use ameax\HashChangeDetector\Models\Hash;
use ameax\HashChangeDetector\Tests\TestModels\TestModel;
use ameax\HashChangeDetector\Tests\TestModels\TestRelationModel;
use Illuminate\Support\Facades\DB;

it('stores full model class name in hashable_type field', function () {
    $model = TestModel::create([
        'name' => 'Test Model',
        'description' => 'Testing model name storage',
        'price' => 100.00,
        'active' => true,
    ]);

    $hash = $model->getCurrentHash();

    // Check the stored model class name
    expect($hash->hashable_type)->toBe(TestModel::class);
    expect($hash->hashable_type)->toBe('ameax\HashChangeDetector\Tests\TestModels\TestModel');

    // Check raw database value
    $rawHash = DB::table('hashes')
        ->where('hashable_id', $model->id)
        ->where('hashable_type', TestModel::class)
        ->first();

    expect($rawHash->hashable_type)->toBe('ameax\HashChangeDetector\Tests\TestModels\TestModel');
});

it('stores parent model class name in hash_parents table', function () {
    $parent = TestModel::create([
        'name' => 'Parent Model',
        'description' => 'Parent',
        'price' => 100.00,
        'active' => true,
    ]);

    $child = TestRelationModel::create([
        'test_model_id' => $parent->id,
        'key' => 'child-key',
        'value' => 'child-value',
        'order' => 1,
    ]);

    // Update child hash which will store parent references
    $child->updateHash();

    $childHash = $child->getCurrentHash();
    $parentRefs = $childHash->parents;

    // Check parent model type storage in hash_parents table
    expect($parentRefs)->toHaveCount(1);

    $parentRef = $parentRefs->first();
    expect($parentRef->parent_model_type)->toBe(TestModel::class);
    expect($parentRef->parent_model_type)->toBe('ameax\HashChangeDetector\Tests\TestModels\TestModel');
    expect($parentRef->parent_model_id)->toBe($parent->id);
    expect($parentRef->relation_name)->toBe('testModel');
});

it('uses full class name in direct database detection queries', function () {
    $model = TestModel::create([
        'name' => 'Detection Test',
        'description' => 'Testing detection',
        'price' => 50.00,
        'active' => false,
    ]);

    // Update directly in database
    DB::table('test_models')
        ->where('id', $model->id)
        ->update(['name' => 'Changed Name']);

    // Check what the detection query would look like
    $hashesTable = config('laravel-hash-change-detector.tables.hashes', 'hashes');
    $result = DB::table('test_models as m')
        ->leftJoin($hashesTable.' as h', function ($join) {
            $join->on('h.hashable_id', '=', 'm.id')
                ->where('h.hashable_type', '=', TestModel::class);
        })
        ->select('m.id', 'h.hashable_type')
        ->where('m.id', $model->id)
        ->first();

    expect($result->hashable_type)->toBe('ameax\HashChangeDetector\Tests\TestModels\TestModel');
});

it('handles namespaced models correctly in detection', function () {
    // Create models with different namespaces
    $model1 = TestModel::create(['name' => 'Model 1', 'description' => 'Test', 'price' => 10, 'active' => true]);
    $model2 = TestRelationModel::create(['test_model_id' => $model1->id, 'key' => 'key1', 'value' => 'val1', 'order' => 1]);

    // Check stored class names
    $hashes = Hash::whereIn('hashable_id', [$model1->id, $model2->id])
        ->get()
        ->pluck('hashable_type')
        ->unique()
        ->sort()
        ->values();

    expect($hashes)->toHaveCount(2);
    expect($hashes[0])->toBe('ameax\HashChangeDetector\Tests\TestModels\TestModel');
    expect($hashes[1])->toBe('ameax\HashChangeDetector\Tests\TestModels\TestRelationModel');
});
