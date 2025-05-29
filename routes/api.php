<?php

declare(strict_types=1);

use ameax\HashChangeDetector\Http\Controllers\HashChangeDetectorApiController;
use ameax\HashChangeDetector\Http\Controllers\PublisherController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Hash Change Detector API Routes
|--------------------------------------------------------------------------
|
| Here are the API routes for the Hash Change Detector package.
| You can include these routes in your application by adding:
| 
| Route::prefix('api/hash-change-detector')
|     ->middleware(['api', 'auth:api'])
|     ->group(base_path('vendor/ameax/laravel-hash-change-detector/routes/api.php'));
|
*/

// Model-specific endpoints
Route::prefix('models/{modelType}/{modelId}')->group(function () {
    Route::get('hash', [HashChangeDetectorApiController::class, 'getModelHash'])
        ->name('hash-change-detector.api.model.hash');
    
    Route::post('publish', [HashChangeDetectorApiController::class, 'forcePublish'])
        ->name('hash-change-detector.api.model.publish');
    
    Route::get('publishes', [HashChangeDetectorApiController::class, 'getPublishHistory'])
        ->name('hash-change-detector.api.model.publishes');
});

// Publisher CRUD management
Route::prefix('publishers')->group(function () {
    // CRUD operations
    Route::get('/', [PublisherController::class, 'index'])
        ->name('hash-change-detector.api.publishers.index');
    
    Route::post('/', [PublisherController::class, 'store'])
        ->name('hash-change-detector.api.publishers.store');
    
    Route::get('types', [PublisherController::class, 'getTypes'])
        ->name('hash-change-detector.api.publishers.types');
    
    Route::post('test', [PublisherController::class, 'test'])
        ->name('hash-change-detector.api.publishers.test');
    
    Route::patch('bulk', [PublisherController::class, 'bulkUpdate'])
        ->name('hash-change-detector.api.publishers.bulk-update');
    
    Route::get('{id}', [PublisherController::class, 'show'])
        ->name('hash-change-detector.api.publishers.show');
    
    Route::put('{id}', [PublisherController::class, 'update'])
        ->name('hash-change-detector.api.publishers.update');
    
    Route::patch('{id}', [PublisherController::class, 'update'])
        ->name('hash-change-detector.api.publishers.patch');
    
    Route::delete('{id}', [PublisherController::class, 'destroy'])
        ->name('hash-change-detector.api.publishers.destroy');
    
    Route::get('{id}/stats', [PublisherController::class, 'stats'])
        ->name('hash-change-detector.api.publishers.stats');
});

// Operations
Route::post('detect-changes', [HashChangeDetectorApiController::class, 'detectChanges'])
    ->name('hash-change-detector.api.detect-changes');

Route::post('retry-publishes', [HashChangeDetectorApiController::class, 'retryPublishes'])
    ->name('hash-change-detector.api.retry-publishes');

Route::post('initialize-hashes', [HashChangeDetectorApiController::class, 'initializeHashes'])
    ->name('hash-change-detector.api.initialize-hashes');

// Statistics
Route::get('stats', [HashChangeDetectorApiController::class, 'getStats'])
    ->name('hash-change-detector.api.stats');