<?php

use ameax\HashChangeDetector\Facades\HashChangeDetector;
use ameax\HashChangeDetector\Models\Publisher;
use ameax\HashChangeDetector\Publishers\LogPublisher;
use ameax\HashChangeDetector\Tests\TestModels\TestModel;

it('registers publishers through facade', function () {
    HashChangeDetector::registerPublisher(
        'facade-test',
        TestModel::class,
        LogPublisher::class
    );
    
    $publisher = Publisher::where('name', 'facade-test')->first();
    
    expect($publisher)->not->toBeNull();
    expect($publisher->model_type)->toBe(TestModel::class);
    expect($publisher->publisher_class)->toBe(LogPublisher::class);
    expect($publisher->status)->toBe('active');
});

it('activates publisher through facade', function () {
    $publisher = Publisher::create([
        'name' => 'Inactive Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'inactive',
    ]);
    
    HashChangeDetector::activatePublisher($publisher->id);
    
    $publisher->refresh();
    expect($publisher->status)->toBe('active');
});

it('deactivates publisher through facade', function () {
    $publisher = Publisher::create([
        'name' => 'Active Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);
    
    HashChangeDetector::deactivatePublisher($publisher->id);
    
    $publisher->refresh();
    expect($publisher->status)->toBe('inactive');
});

it('gets publishers for model through facade', function () {
    Publisher::create([
        'name' => 'First Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);
    
    Publisher::create([
        'name' => 'Second Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogPublisher::class,
        'status' => 'inactive',
    ]);
    
    Publisher::create([
        'name' => 'Other Model Publisher',
        'model_type' => 'App\\Models\\OtherModel',
        'publisher_class' => LogPublisher::class,
        'status' => 'active',
    ]);
    
    $publishers = HashChangeDetector::getPublishersForModel(TestModel::class);
    
    expect($publishers)->toHaveCount(2);
    expect($publishers->pluck('name')->toArray())->toContain('First Publisher');
    expect($publishers->pluck('name')->toArray())->toContain('Second Publisher');
});

it('handles invalid publisher ID for activation', function () {
    $result = HashChangeDetector::activatePublisher(99999);
    
    expect($result)->toBeFalse();
});

it('handles invalid publisher ID for deactivation', function () {
    $result = HashChangeDetector::deactivatePublisher(99999);
    
    expect($result)->toBeFalse();
});