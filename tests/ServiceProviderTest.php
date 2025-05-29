<?php

use ameax\HashChangeDetector\HashChangeDetectorServiceProvider;
use ameax\HashChangeDetector\Commands\CreatePublisherCommand;
use ameax\HashChangeDetector\Commands\ListPublishersCommand;
use ameax\HashChangeDetector\Commands\TogglePublisherCommand;
use ameax\HashChangeDetector\Commands\DetectChangesCommand;
use ameax\HashChangeDetector\Commands\RetryPublishesCommand;
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