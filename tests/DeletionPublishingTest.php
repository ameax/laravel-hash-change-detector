<?php

declare(strict_types=1);

use ameax\HashChangeDetector\Events\HashableModelDeleted;
use ameax\HashChangeDetector\Jobs\DeletePublishJob;
use ameax\HashChangeDetector\Models\Hash;
use ameax\HashChangeDetector\Models\Publish;
use ameax\HashChangeDetector\Models\Publisher;
use ameax\HashChangeDetector\Publishers\LogDeletePublisher;
use ameax\HashChangeDetector\Tests\TestModels\TestModel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('creates deletion publish records when HashableModelDeleted event is fired', function () {
    // Create a delete publisher
    $publisher = Publisher::create([
        'name' => 'Test Delete Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogDeletePublisher::class,
        'status' => 'active',
    ]);

    // Create a hash record
    $hash = Hash::create([
        'hashable_type' => TestModel::class,
        'hashable_id' => 123,
        'attribute_hash' => 'abc123',
        'composite_hash' => 'xyz789',
    ]);

    // Fire the deletion event
    event(new HashableModelDeleted($hash, TestModel::class, 123));

    // Assert publish record was created
    $publish = Publish::where('publisher_id', $publisher->id)
        ->whereNull('hash_id')
        ->first();

    expect($publish)->not->toBeNull();
    expect($publish->metadata['type'])->toBe('deletion');
    expect($publish->metadata['model_class'])->toBe(TestModel::class);
    expect($publish->metadata['model_id'])->toBe(123);
    expect($publish->metadata['last_known_data']['attribute_hash'])->toBe('abc123');
    expect($publish->metadata['last_known_data']['composite_hash'])->toBe('xyz789');

    // Assert job was dispatched
    Queue::assertPushed(DeletePublishJob::class);
});

it('only creates deletion publishes for publishers that implement DeletePublisher', function () {
    // Create a regular publisher (not a delete publisher)
    $regularPublisher = Publisher::create([
        'name' => 'Regular Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => \ameax\HashChangeDetector\Publishers\LogPublisher::class,
        'status' => 'active',
    ]);

    // Create a delete publisher
    $deletePublisher = Publisher::create([
        'name' => 'Delete Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogDeletePublisher::class,
        'status' => 'active',
    ]);

    $hash = Hash::create([
        'hashable_type' => TestModel::class,
        'hashable_id' => 456,
        'attribute_hash' => 'hash456',
        'composite_hash' => null,
    ]);

    event(new HashableModelDeleted($hash, TestModel::class, 456));

    // Only one publish record should be created (for the delete publisher)
    $publishes = Publish::whereNull('hash_id')->get();
    expect($publishes)->toHaveCount(1);
    expect($publishes->first()->publisher_id)->toBe($deletePublisher->id);
});

it('skips inactive delete publishers', function () {
    $publisher = Publisher::create([
        'name' => 'Inactive Delete Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogDeletePublisher::class,
        'status' => 'inactive',
    ]);

    $hash = Hash::create([
        'hashable_type' => TestModel::class,
        'hashable_id' => 789,
        'attribute_hash' => 'hash789',
        'composite_hash' => null,
    ]);

    event(new HashableModelDeleted($hash, TestModel::class, 789));

    // No publish records should be created
    expect(Publish::whereNull('hash_id')->count())->toBe(0);
    Queue::assertNotPushed(DeletePublishJob::class);
});

it('executes deletion publish job successfully', function () {
    Queue::fake([]); // Don't fake jobs
    Log::spy();

    $publisher = Publisher::create([
        'name' => 'Log Delete Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => LogDeletePublisher::class,
        'status' => 'active',
    ]);

    $publish = Publish::create([
        'hash_id' => null,
        'publisher_id' => $publisher->id,
        'published_hash' => 'deleted-hash',
        'status' => 'pending',
        'metadata' => [
            'type' => 'deletion',
            'model_class' => TestModel::class,
            'model_id' => 999,
            'last_known_data' => [
                'attribute_hash' => 'final-hash',
                'composite_hash' => null,
                'deleted_at' => '2024-01-01T00:00:00Z',
            ],
        ],
    ]);

    $job = new DeletePublishJob($publish);
    $job->handle();

    // Assert log was called
    Log::shouldHaveReceived('info')->once()->with('Model deleted', [
        'model_class' => TestModel::class,
        'model_id' => 999,
        'attribute_hash' => 'final-hash',
        'composite_hash' => null,
        'deleted_at' => '2024-01-01T00:00:00Z',
    ]);

    // Assert publish was marked as published
    $publish->refresh();
    expect($publish->isPublished())->toBeTrue();
    expect($publish->published_at)->not->toBeNull();
});

it('handles deletion publish job failure correctly', function () {
    Queue::fake([]); // Don't fake jobs

    // Create a test delete publisher class that fails
    eval('
        namespace ameax\HashChangeDetector\Tests;
        
        class FailingDeletePublisher implements \ameax\HashChangeDetector\Contracts\DeletePublisher {
            public function publishDeletion(string $modelClass, int $modelId, array $lastKnownData): bool
            {
                return false; // Simulate failure
            }

            public function shouldPublishDeletion(string $modelClass, int $modelId): bool
            {
                return true;
            }

            public function getMaxAttempts(): int
            {
                return 2;
            }
        }
    ');

    $publisher = Publisher::create([
        'name' => 'Failing Delete Publisher',
        'model_type' => TestModel::class,
        'publisher_class' => \ameax\HashChangeDetector\Tests\FailingDeletePublisher::class,
        'status' => 'active',
    ]);

    $publish = Publish::create([
        'hash_id' => null,
        'publisher_id' => $publisher->id,
        'published_hash' => 'fail-hash',
        'status' => 'pending',
        'metadata' => [
            'type' => 'deletion',
            'model_class' => TestModel::class,
            'model_id' => 111,
            'last_known_data' => [],
        ],
    ]);

    $job = new DeletePublishJob($publish);
    $job->handle();

    $publish->refresh();
    expect($publish->isDeferred())->toBeTrue();
    expect($publish->attempts)->toBe(1);
    expect($publish->last_error)->toBe('Delete publisher returned false');
    expect($publish->next_try)->not->toBeNull();
});

it('marks Publisher model methods as delete publisher correctly', function () {
    $regularPublisher = Publisher::create([
        'name' => 'Regular',
        'model_type' => TestModel::class,
        'publisher_class' => \ameax\HashChangeDetector\Publishers\LogPublisher::class,
        'status' => 'active',
    ]);

    $deletePublisher = Publisher::create([
        'name' => 'Delete',
        'model_type' => TestModel::class,
        'publisher_class' => LogDeletePublisher::class,
        'status' => 'active',
    ]);

    expect($regularPublisher->isDeletePublisher())->toBeFalse();
    expect($regularPublisher->getDeletePublisherInstance())->toBeNull();

    expect($deletePublisher->isDeletePublisher())->toBeTrue();
    expect($deletePublisher->getDeletePublisherInstance())->toBeInstanceOf(\ameax\HashChangeDetector\Contracts\DeletePublisher::class);
});
