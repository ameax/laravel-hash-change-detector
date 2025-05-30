<?php

declare(strict_types=1);

use ameax\HashChangeDetector\HashChangeDetectorServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;

it('registers service provider', function () {
    $provider = $this->app->getProvider(HashChangeDetectorServiceProvider::class);

    expect($provider)->toBeInstanceOf(HashChangeDetectorServiceProvider::class);
});

it('registers all commands', function () {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('hash-detector:publisher:create');
    expect($commands)->toHaveKey('hash-detector:publisher:list');
    expect($commands)->toHaveKey('hash-detector:publisher:toggle');
    expect($commands)->toHaveKey('hash-detector:detect-changes');
    expect($commands)->toHaveKey('hash-detector:retry-publishes');
});

it('registers event listeners', function () {
    $listeners = Event::getRawListeners();

    expect($listeners)->toHaveKey('ameax\HashChangeDetector\Events\HashChanged');
    expect($listeners)->toHaveKey('ameax\HashChangeDetector\Events\RelatedModelUpdated');
});

it('merges config correctly', function () {
    $config = config('hash-change-detector');

    expect($config)->toBeArray();
    expect($config)->toHaveKey('tables');
    expect($config)->toHaveKey('hash_algorithm');
    expect($config)->toHaveKey('retry_intervals');
    expect($config['tables'])->toHaveKey('hashes');
    expect($config['tables'])->toHaveKey('publishers');
    expect($config['tables'])->toHaveKey('publishes');
});

it('resolves facade to correct instance', function () {
    $instance = \ameax\HashChangeDetector\Facades\HashChangeDetector::getFacadeRoot();

    expect($instance)->toBeInstanceOf(\ameax\HashChangeDetector\HashChangeDetector::class);
});

it('loads migrations in test environment', function () {
    // Check that the migration file exists in the expected location
    $migrationPath = __DIR__ . '/../database/migrations/0000_00_00_000000_create_hash_change_detector_tables.php';
    expect(file_exists($migrationPath))->toBeTrue();
    
    // Check that tables are created (which proves migrations are loaded)
    $hashesTable = config('hash-change-detector.tables.hashes', 'hashes');
    $publishersTable = config('hash-change-detector.tables.publishers', 'publishers');
    $publishesTable = config('hash-change-detector.tables.publishes', 'publishes');
    
    expect(\Schema::hasTable($hashesTable))->toBeTrue();
    expect(\Schema::hasTable($publishersTable))->toBeTrue();
    expect(\Schema::hasTable($publishesTable))->toBeTrue();
});
