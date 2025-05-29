<?php

use ameax\HashChangeDetector\Jobs\DetectChangesJob;
use ameax\HashChangeDetector\Models\Hash;
use ameax\HashChangeDetector\Tests\TestModels\TestModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

it('detects changes made directly in database', function () {
    // Create model normally to establish initial hash
    $model = TestModel::create([
        'name' => 'Original Name',
        'description' => 'Original Description',
        'price' => 50.00,
        'active' => true,
    ]);

    $originalHash = $model->getCurrentHash();

    // Update directly in database (simulating external change)
    DB::table('test_models')
        ->where('id', $model->id)
        ->update(['name' => 'Changed Name']);

    // Run detection job
    $job = new DetectChangesJob(TestModel::class);
    $job->handle();

    // Check hash was updated
    $newHash = Hash::where('hashable_type', TestModel::class)
        ->where('hashable_id', $model->id)
        ->first();

    expect($newHash->attribute_hash)->not->toBe($originalHash->attribute_hash);
    // Attributes are sorted: active, description, name, price
    expect($newHash->attribute_hash)->toBe(md5('1|Original Description|Changed Name|50.00'));
});

it('detects multiple changes in bulk', function () {
    // Create multiple models
    $models = collect([
        TestModel::create(['name' => 'Product 1', 'description' => 'Desc 1', 'price' => 10, 'active' => true]),
        TestModel::create(['name' => 'Product 2', 'description' => 'Desc 2', 'price' => 20, 'active' => true]),
        TestModel::create(['name' => 'Product 3', 'description' => 'Desc 3', 'price' => 30, 'active' => true]),
    ]);

    $originalHashes = $models->map(fn($m) => $m->getCurrentHash()->attribute_hash);

    // Update all directly in database
    DB::table('test_models')
        ->whereIn('id', $models->pluck('id'))
        ->update(['active' => false]);

    // Run detection
    $job = new DetectChangesJob(TestModel::class);
    $job->handle();

    // Check all hashes were updated
    $models->each(function ($model, $index) use ($originalHashes) {
        $newHash = Hash::where('hashable_type', TestModel::class)
            ->where('hashable_id', $model->id)
            ->first();

        expect($newHash->attribute_hash)->not->toBe($originalHashes[$index]);
    });
});

it('creates hash for models without hash records', function () {
    // Create model directly in database (no hash record)
    $id = DB::table('test_models')->insertGetId([
        'name' => 'Direct Insert',
        'description' => 'No Hash',
        'price' => 99.99,
        'active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Verify no hash exists
    $hash = Hash::where('hashable_type', TestModel::class)
        ->where('hashable_id', $id)
        ->first();
    expect($hash)->toBeNull();

    // Run detection
    $job = new DetectChangesJob(TestModel::class);
    $job->handle();

    // Check hash was created
    $hash = Hash::where('hashable_type', TestModel::class)
        ->where('hashable_id', $id)
        ->first();

    expect($hash)->not->toBeNull();
    // Attributes are sorted: active, description, name, price
    expect($hash->attribute_hash)->toBe(md5('1|No Hash|Direct Insert|99.99'));
});

it('correctly calculates hash using database functions', function () {
    $model = TestModel::create([
        'name' => 'Database Test',
        'description' => null,
        'price' => 123.45,
        'active' => false,
    ]);

    // Get the hash calculated by PHP
    $phpHash = $model->getCurrentHash()->attribute_hash;

    // Delete the hash to force recalculation
    Hash::where('hashable_type', TestModel::class)
        ->where('hashable_id', $model->id)
        ->delete();

    // Run database-based detection
    $job = new DetectChangesJob(TestModel::class);
    $job->handle();

    // Get the hash calculated by database
    $databaseHash = Hash::where('hashable_type', TestModel::class)
        ->where('hashable_id', $model->id)
        ->first();

    // Both methods should produce identical hashes
    expect($databaseHash->attribute_hash)->toBe($phpHash);
    // Attributes are sorted: active, description, name, price
    expect($databaseHash->attribute_hash)->toBe(md5('0||Database Test|123.45'));
});

it('handles all hashable attribute types correctly in database', function () {
    // Test various data types
    $testCases = [
        ['name' => 'String Test', 'description' => 'Regular string', 'price' => 100, 'active' => true],
        ['name' => 'Null Test', 'description' => null, 'price' => 0, 'active' => false],
        ['name' => 'Decimal Test', 'description' => 'Price test', 'price' => 99.99, 'active' => true],
        ['name' => 'Boolean False', 'description' => 'Bool test', 'price' => 50, 'active' => false],
        ['name' => 'Boolean True', 'description' => 'Bool test', 'price' => 50, 'active' => true],
    ];

    foreach ($testCases as $data) {
        $model = TestModel::create($data);
        $phpHash = $model->getCurrentHash()->attribute_hash;

        // Delete hash and recalculate with database
        Hash::where('hashable_type', TestModel::class)
            ->where('hashable_id', $model->id)
            ->delete();

        $job = new DetectChangesJob(TestModel::class);
        $job->handle();

        $databaseHash = Hash::where('hashable_type', TestModel::class)
            ->where('hashable_id', $model->id)
            ->first();

        expect($databaseHash->attribute_hash)->toBe($phpHash);
    }
});