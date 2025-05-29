<?php

use ameax\HashChangeDetector\Models\Hash;
use ameax\HashChangeDetector\Models\Publisher;
use ameax\HashChangeDetector\Models\Publish;
use ameax\HashChangeDetector\Tests\TestModels\TestModel;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('reads table names from config', function () {
    // Test that models can read table config
    $hashTable = config('hash-change-detector.tables.hashes');
    $publisherTable = config('hash-change-detector.tables.publishers');
    $publishTable = config('hash-change-detector.tables.publishes');
    
    expect($hashTable)->toBe('hashes');
    expect($publisherTable)->toBe('publishers');
    expect($publishTable)->toBe('publishes');
});

it('reads hash algorithm from config', function () {
    $algorithm = config('hash-change-detector.hash_algorithm');
    expect($algorithm)->toBe('md5');
});

it('reads queue name from config', function () {
    $queue = config('hash-change-detector.queue');
    expect($queue)->toBeNull(); // Queue is optional and defaults to null
});

it('reads retry intervals from config', function () {
    $intervals = config('hash-change-detector.retry_intervals');
    
    expect($intervals)->toBeArray();
    expect($intervals[1])->toBe(30);
    expect($intervals[2])->toBe(300);
    expect($intervals[3])->toBe(21600);
});