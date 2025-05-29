<?php

use ameax\HashChangeDetector\Jobs\PublishModelJob;
use ameax\HashChangeDetector\Models\Publisher;
use ameax\HashChangeDetector\Models\Publish;
use ameax\HashChangeDetector\Tests\TestModels\TestModel;
use ameax\HashChangeDetector\Tests\TestModels\TestRelationModel;
use Illuminate\Database\Eloquent\Model;

// Test publisher that throws exceptions
class ExceptionThrowingPublisher extends \ameax\HashChangeDetector\Publishers\BasePublisher
{
    public static string $exceptionType = 'generic';
    
    public function publish(Model $model, array $data): bool
    {
        if (self::$exceptionType === 'timeout') {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        }
        
        if (self::$exceptionType === 'network') {
            throw new \Exception('Network error occurred');
        }
        
        throw new \RuntimeException('Generic runtime error');
    }
}

beforeEach(function () {
    ExceptionThrowingPublisher::$exceptionType = 'generic';
    \Illuminate\Support\Facades\Queue::fake();
});

it('handles exceptions during publishing', function () {
    $publisher = Publisher::create([
        'name' => 'Exception Publisher 1',
        'model_type' => TestModel::class,
        'publisher_class' => ExceptionThrowingPublisher::class,
        'status' => 'active',
    ]);

    $model = TestModel::create([
        'name' => 'Test Product',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $hash = $model->getCurrentHash();
    
    $publish = Publish::create([
        'hash_id' => $hash->id,
        'publisher_id' => $publisher->id,
        'published_hash' => $hash->composite_hash,
        'status' => 'pending',
        'attempts' => 0,
    ]);

    // Execute the job
    $job = new PublishModelJob($publish);
    $job->handle();

    $publish->refresh();
    
    expect($publish->status)->toBe('deferred');
    expect($publish->last_error)->toContain('Generic runtime error');
});

it('handles network timeout exceptions', function () {
    ExceptionThrowingPublisher::$exceptionType = 'timeout';
    
    $publisher = Publisher::create([
        'name' => 'Timeout Publisher 2',
        'model_type' => TestModel::class,
        'publisher_class' => ExceptionThrowingPublisher::class,
        'status' => 'active',
    ]);

    $model = TestModel::create([
        'name' => 'Test Product 2',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $hash = $model->getCurrentHash();
    
    $publish = Publish::create([
        'hash_id' => $hash->id,
        'publisher_id' => $publisher->id,
        'published_hash' => $hash->composite_hash,
        'status' => 'pending',
        'attempts' => 0,
    ]);

    // Execute the job
    $job = new PublishModelJob($publish);
    $job->handle();

    $publish->refresh();
    
    expect($publish->status)->toBe('deferred');
    expect($publish->last_error)->toContain('Connection timed out');
});

it('handles invalid publisher class gracefully', function () {
    $publisher = Publisher::create([
        'name' => 'Invalid Publisher 3',
        'model_type' => TestModel::class,
        'publisher_class' => 'NonExistentPublisherClass',
        'status' => 'active',
    ]);

    $model = TestModel::create([
        'name' => 'Test Product 3',
        'description' => 'Test Description',
        'price' => 99.99,
        'active' => true,
    ]);

    $hash = $model->getCurrentHash();
    
    $publish = Publish::create([
        'hash_id' => $hash->id,
        'publisher_id' => $publisher->id,
        'published_hash' => $hash->composite_hash,
        'status' => 'pending',
        'attempts' => 0,
    ]);

    // Execute the job
    $job = new PublishModelJob($publish);
    $job->handle();

    $publish->refresh();
    
    expect($publish->status)->toBe('deferred');
    expect($publish->last_error)->toContain('Target class [NonExistentPublisherClass] does not exist');
});

it('handles command errors for invalid publisher ID', function () {
    $this->artisan('hash-detector:publisher:toggle', ['id' => 99999])
        ->assertExitCode(1);
});

it('validates publisher class exists in create command', function () {
    $result = $this->artisan('hash-detector:publisher:create', [
        'name' => 'Invalid Publisher',
        'model' => TestModel::class,
        'publisher' => 'App\\Publishers\\NonExistentPublisher',
    ]);
    
    $result->expectsOutput('Publisher class App\\Publishers\\NonExistentPublisher does not exist.')
        ->assertFailed();
});

it('handles model with circular parent relationships gracefully', function () {
    // Test that the system handles circular references without infinite loops
    $model = TestModel::create([
        'name' => 'Circular Reference',
        'description' => 'Test',
        'price' => 100,
        'active' => true,
    ]);
    
    // Create a child that could theoretically create a circular reference
    $child = TestRelationModel::create([
        'test_model_id' => $model->id,
        'value' => 'Child',
        'key' => 'childkey',
    ]);
    
    // Should not throw exception
    $model->updateHash();
    $child->updateHash();
    
    expect($model->getCurrentHash())->not->toBeNull();
    expect($child->getCurrentHash())->not->toBeNull();
});