<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Http\Controllers;

use ameax\HashChangeDetector\Jobs\DetectChangesJob;
use ameax\HashChangeDetector\Jobs\PublishModelJob;
use ameax\HashChangeDetector\Models\Hash;
use ameax\HashChangeDetector\Models\Publish;
use ameax\HashChangeDetector\Models\Publisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class HashChangeDetectorApiController extends Controller
{
    /**
     * Get the current hash status for a model.
     * 
     * GET /api/hash-change-detector/models/{type}/{id}/hash
     */
    public function getModelHash(string $modelType, int $modelId): JsonResponse
    {
        $modelClass = $this->resolveModelClass($modelType);
        
        if (!class_exists($modelClass)) {
            return response()->json(['error' => 'Model type not found'], 404);
        }

        $hash = Hash::where('hashable_type', $modelClass)
            ->where('hashable_id', $modelId)
            ->first();

        if (!$hash) {
            return response()->json(['error' => 'Hash not found for model'], 404);
        }

        return response()->json([
            'model_type' => $modelClass,
            'model_id' => $modelId,
            'attribute_hash' => $hash->attribute_hash,
            'composite_hash' => $hash->composite_hash,
            'is_main_model' => $hash->isMainModel(),
            'parent_model' => $hash->main_model_type ? [
                'type' => $hash->main_model_type,
                'id' => $hash->main_model_id,
            ] : null,
            'updated_at' => $hash->updated_at->toIso8601String(),
        ]);
    }

    /**
     * Force publish a model to specific publishers without hash change.
     * 
     * POST /api/hash-change-detector/models/{type}/{id}/publish
     * Body: { "publisher_ids": [1, 2, 3] } or { "publisher_names": ["log", "api"] }
     */
    public function forcePublish(Request $request, string $modelType, int $modelId): JsonResponse
    {
        $modelClass = $this->resolveModelClass($modelType);
        
        if (!class_exists($modelClass)) {
            return response()->json(['error' => 'Model type not found'], 404);
        }

        $model = $modelClass::find($modelId);
        if (!$model) {
            return response()->json(['error' => 'Model not found'], 404);
        }

        // Get the hash record
        $hash = $model->getCurrentHash();
        if (!$hash) {
            return response()->json(['error' => 'Model has no hash record'], 400);
        }

        // Determine which publishers to use
        $publisherIds = $request->input('publisher_ids', []);
        $publisherNames = $request->input('publisher_names', []);

        $query = Publisher::active()->forModel($modelClass);

        if (!empty($publisherIds)) {
            $query->whereIn('id', $publisherIds);
        } elseif (!empty($publisherNames)) {
            $query->whereIn('name', $publisherNames);
        }

        $publishers = $query->get();

        if ($publishers->isEmpty()) {
            return response()->json(['error' => 'No active publishers found'], 400);
        }

        $dispatched = [];

        foreach ($publishers as $publisher) {
            // Create or update publish record
            $publish = Publish::updateOrCreate([
                'hash_id' => $hash->id,
                'publisher_id' => $publisher->id,
            ], [
                'published_hash' => $hash->composite_hash ?? $hash->attribute_hash,
                'status' => 'pending',
                'attempts' => 0,
                'last_error' => null,
                'next_try' => null,
            ]);

            // Dispatch the job
            PublishModelJob::dispatch($publish);
            
            $dispatched[] = [
                'publisher_id' => $publisher->id,
                'publisher_name' => $publisher->name,
                'publish_id' => $publish->id,
            ];
        }

        return response()->json([
            'message' => 'Publish jobs dispatched',
            'model' => [
                'type' => $modelClass,
                'id' => $modelId,
            ],
            'publishers' => $dispatched,
        ]);
    }

    /**
     * Get publish history for a model.
     * 
     * GET /api/hash-change-detector/models/{type}/{id}/publishes
     */
    public function getPublishHistory(Request $request, string $modelType, int $modelId): JsonResponse
    {
        $modelClass = $this->resolveModelClass($modelType);
        
        if (!class_exists($modelClass)) {
            return response()->json(['error' => 'Model type not found'], 404);
        }

        $hash = Hash::where('hashable_type', $modelClass)
            ->where('hashable_id', $modelId)
            ->first();

        if (!$hash) {
            return response()->json(['error' => 'Hash not found for model'], 404);
        }

        $query = $hash->publishes()->with('publisher');

        // Optional filters
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('publisher_id')) {
            $query->where('publisher_id', $request->input('publisher_id'));
        }

        $publishes = $query->latest()->paginate($request->input('per_page', 50));

        return response()->json($publishes);
    }

    /**
     * Get all publishers for a model type.
     * 
     * GET /api/hash-change-detector/publishers?model_type=App\Models\User
     */
    public function getPublishers(Request $request): JsonResponse
    {
        $query = Publisher::query();

        if ($request->has('model_type')) {
            $modelType = $this->resolveModelClass($request->input('model_type'));
            $query->forModel($modelType);
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $publishers = $query->get();

        return response()->json([
            'publishers' => $publishers->map(function ($publisher) {
                return [
                    'id' => $publisher->id,
                    'name' => $publisher->name,
                    'model_type' => $publisher->model_type,
                    'publisher_class' => $publisher->publisher_class,
                    'status' => $publisher->status,
                    'created_at' => $publisher->created_at->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * Trigger change detection for specific model or all models.
     * 
     * POST /api/hash-change-detector/detect-changes
     * Body: { "model_type": "App\\Models\\User" } (optional)
     */
    public function detectChanges(Request $request): JsonResponse
    {
        $modelType = null;

        if ($request->has('model_type')) {
            $modelType = $this->resolveModelClass($request->input('model_type'));
            
            if (!class_exists($modelType)) {
                return response()->json(['error' => 'Model type not found'], 404);
            }
        }

        DetectChangesJob::dispatch($modelType);

        return response()->json([
            'message' => 'Change detection job dispatched',
            'model_type' => $modelType ?? 'all',
        ]);
    }

    /**
     * Get statistics about hash tracking.
     * 
     * GET /api/hash-change-detector/stats
     */
    public function getStats(Request $request): JsonResponse
    {
        $stats = [
            'total_hashes' => Hash::count(),
            'main_models' => Hash::whereNull('main_model_type')->count(),
            'related_models' => Hash::whereNotNull('main_model_type')->count(),
            'total_publishers' => Publisher::count(),
            'active_publishers' => Publisher::where('status', 'active')->count(),
            'publishes_by_status' => Publish::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status'),
        ];

        if ($request->has('model_type')) {
            $modelType = $this->resolveModelClass($request->input('model_type'));
            
            $stats['model_specific'] = [
                'model_type' => $modelType,
                'total_models' => Hash::where('hashable_type', $modelType)->count(),
                'publishers' => Publisher::forModel($modelType)->count(),
            ];
        }

        return response()->json($stats);
    }

    /**
     * Retry failed or deferred publishes.
     * 
     * POST /api/hash-change-detector/retry-publishes
     * Body: { "publisher_id": 1 } (optional)
     */
    public function retryPublishes(Request $request): JsonResponse
    {
        $query = Publish::whereIn('status', ['failed', 'deferred']);

        if ($request->has('publisher_id')) {
            $query->where('publisher_id', $request->input('publisher_id'));
        }

        // Only retry those that are due
        $query->where(function ($q) {
            $q->whereNull('next_try')
                ->orWhere('next_try', '<=', now());
        });

        $publishes = $query->limit(100)->get();

        foreach ($publishes as $publish) {
            $publish->update([
                'status' => 'pending',
                'attempts' => 0,
                'last_error' => null,
                'next_try' => null,
            ]);
            
            PublishModelJob::dispatch($publish);
        }

        return response()->json([
            'message' => 'Retry jobs dispatched',
            'count' => $publishes->count(),
        ]);
    }

    /**
     * Update publisher status.
     * 
     * PATCH /api/hash-change-detector/publishers/{id}
     * Body: { "status": "active" } or { "status": "inactive" }
     */
    public function updatePublisher(Request $request, int $publisherId): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:active,inactive',
        ]);

        $publisher = Publisher::find($publisherId);
        if (!$publisher) {
            return response()->json(['error' => 'Publisher not found'], 404);
        }

        $publisher->update(['status' => $request->input('status')]);

        return response()->json([
            'message' => 'Publisher updated',
            'publisher' => [
                'id' => $publisher->id,
                'name' => $publisher->name,
                'status' => $publisher->status,
            ],
        ]);
    }

    /**
     * Initialize hashes for models without hash records.
     * 
     * POST /api/hash-change-detector/initialize-hashes
     * Body: { "model_type": "App\\Models\\User", "chunk_size": 100 }
     */
    public function initializeHashes(Request $request): JsonResponse
    {
        $request->validate([
            'model_type' => 'required|string',
            'chunk_size' => 'integer|min:1|max:1000',
        ]);

        $modelType = $this->resolveModelClass($request->input('model_type'));
        
        if (!class_exists($modelType)) {
            return response()->json(['error' => 'Model type not found'], 404);
        }

        $model = new $modelType;
        
        if (!method_exists($model, 'getHashableAttributes')) {
            return response()->json(['error' => 'Model does not implement Hashable contract'], 400);
        }

        $chunkSize = $request->input('chunk_size', 100);
        $initialized = 0;

        // Find models without hashes
        $modelType::query()
            ->whereNotExists(function ($query) use ($modelType) {
                $query->select('id')
                    ->from(config('laravel-hash-change-detector.tables.hashes', 'hashes'))
                    ->whereColumn('hashable_id', (new $modelType)->getTable() . '.id')
                    ->where('hashable_type', $modelType);
            })
            ->chunk($chunkSize, function ($models) use (&$initialized) {
                foreach ($models as $model) {
                    $model->updateHash();
                    $initialized++;
                }
            });

        return response()->json([
            'message' => 'Hash initialization completed',
            'model_type' => $modelType,
            'initialized_count' => $initialized,
        ]);
    }

    /**
     * Resolve model class from short name or full class name.
     */
    public function resolveModelClass(string $modelType): string
    {
        // If it's already a full class name with namespace, return it
        if (str_contains($modelType, '\\')) {
            return $modelType;
        }

        // Otherwise, try to resolve from common namespaces
        $namespaces = [
            'App\\Models\\',
            'App\\',
            'ameax\\HashChangeDetector\\Tests\\TestModels\\',
        ];

        foreach ($namespaces as $namespace) {
            $fullClass = $namespace . $modelType;
            if (class_exists($fullClass)) {
                return $fullClass;
            }
        }

        // Return as-is if not found
        return $modelType;
    }
}