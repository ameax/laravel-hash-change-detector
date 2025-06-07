<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Http\Controllers;

use ameax\HashChangeDetector\Jobs\DetectChangesJob;
use ameax\HashChangeDetector\Jobs\PublishModelJob;
use ameax\HashChangeDetector\Models\Hash;
use ameax\HashChangeDetector\Models\HashDependent;
use ameax\HashChangeDetector\Models\Publish;
use ameax\HashChangeDetector\Models\Publisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Laravel Hash Change Detector API',
    description: 'API for tracking and publishing model changes based on hash comparisons. This package provides a robust system for detecting changes in Laravel models by computing hashes of their attributes and related models, then publishing those changes to various destinations.',
    contact: new OA\Contact(
        name: 'API Support',
        email: 'support@example.com'
    ),
    license: new OA\License(
        name: 'MIT',
        url: 'https://opensource.org/licenses/MIT'
    )
)]
#[OA\Server(
    url: '/api/hash-change-detector',
    description: 'Hash Change Detector API Server'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    description: 'Enter your bearer token in the format **Bearer &lt;token&gt;**',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
#[OA\Tag(
    name: 'Hash Management',
    description: 'Operations related to hash tracking and change detection'
)]
#[OA\Tag(
    name: 'Publishing',
    description: 'Operations related to publishing model changes'
)]
#[OA\Tag(
    name: 'Publisher Management',
    description: 'CRUD operations for managing publishers'
)]
#[OA\Tag(
    name: 'Statistics',
    description: 'API statistics and monitoring'
)]
class HashChangeDetectorApiController extends Controller
{
    /**
     * Get the current hash status for a model.
     *
     * GET /api/hash-change-detector/models/{type}/{id}/hash
     */
    #[OA\Get(
        path: '/models/{type}/{id}/hash',
        summary: 'Get the current hash status for a model',
        security: [['bearerAuth' => []]],
        tags: ['Hash Management'],
        parameters: [
            new OA\Parameter(
                name: 'type',
                in: 'path',
                required: true,
                description: 'Model type (short name or full class name)',
                schema: new OA\Schema(type: 'string'),
                example: 'User'
            ),
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Model ID',
                schema: new OA\Schema(type: 'integer'),
                example: 1
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'model_type', type: 'string', example: 'App\\Models\\User'),
                        new OA\Property(property: 'model_id', type: 'integer', example: 1),
                        new OA\Property(property: 'attribute_hash', type: 'string', example: 'a1b2c3d4e5f6...'),
                        new OA\Property(property: 'composite_hash', type: 'string', nullable: true, example: 'f6e5d4c3b2a1...'),
                        new OA\Property(property: 'has_dependents', type: 'boolean', example: false),
                        new OA\Property(
                            property: 'dependent_models',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'type', type: 'string'),
                                    new OA\Property(property: 'id', type: 'integer'),
                                    new OA\Property(property: 'relation', type: 'string'),
                                ]
                            )
                        ),
                        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2024-01-01T00:00:00.000000Z'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Model type or hash not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Model type not found'),
                    ]
                )
            ),
        ]
    )]
    public function getModelHash(string $modelType, int $modelId): JsonResponse
    {
        $modelClass = $this->resolveModelClass($modelType);

        if (! class_exists($modelClass)) {
            return response()->json(['error' => 'Model type not found'], 404);
        }

        $hash = Hash::where('hashable_type', $modelClass)
            ->where('hashable_id', $modelId)
            ->first();

        if (! $hash) {
            return response()->json(['error' => 'Hash not found for model'], 404);
        }

        return response()->json([
            'model_type' => $modelClass,
            'model_id' => $modelId,
            'attribute_hash' => $hash->attribute_hash,
            'composite_hash' => $hash->composite_hash,
            'has_dependents' => $hash->hasDependents(),
            'dependent_models' => $hash->dependents->map(function (HashDependent $dependent) {
                return [
                    'type' => $dependent->getAttribute('dependent_model_type'),
                    'id' => $dependent->getAttribute('dependent_model_id'),
                    'relation' => $dependent->getAttribute('relation_name'),
                ];
            }),
            'updated_at' => $hash->updated_at->toIso8601String(),
        ]);
    }

    /**
     * Force publish a model to specific publishers without hash change.
     *
     * POST /api/hash-change-detector/models/{type}/{id}/publish
     * Body: { "publisher_ids": [1, 2, 3] } or { "publisher_names": ["log", "api"] }
     */
    #[OA\Post(
        path: '/models/{type}/{id}/publish',
        summary: 'Force publish a model to specific publishers',
        description: 'Publishes a model to specified publishers without requiring a hash change',
        tags: ['Publishing'],
        parameters: [
            new OA\Parameter(
                name: 'type',
                in: 'path',
                required: true,
                description: 'Model type',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Model ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            description: 'Publisher selection',
            content: new OA\JsonContent(
                oneOf: [
                    new OA\Schema(
                        properties: [
                            new OA\Property(
                                property: 'publisher_ids',
                                type: 'array',
                                items: new OA\Items(type: 'integer'),
                                example: [1, 2, 3]
                            ),
                        ]
                    ),
                    new OA\Schema(
                        properties: [
                            new OA\Property(
                                property: 'publisher_names',
                                type: 'array',
                                items: new OA\Items(type: 'string'),
                                example: ['log', 'api']
                            ),
                        ]
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Publish jobs dispatched successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Publish jobs dispatched'),
                        new OA\Property(
                            property: 'model',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'type', type: 'string'),
                                new OA\Property(property: 'id', type: 'integer'),
                            ]
                        ),
                        new OA\Property(
                            property: 'publishers',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'publisher_id', type: 'integer'),
                                    new OA\Property(property: 'publisher_name', type: 'string'),
                                    new OA\Property(property: 'publish_id', type: 'integer'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Model not found'
            ),
        ]
    )]
    public function forcePublish(Request $request, string $modelType, int $modelId): JsonResponse
    {
        $modelClass = $this->resolveModelClass($modelType);

        if (! class_exists($modelClass)) {
            return response()->json(['error' => 'Model type not found'], 404);
        }

        $model = $modelClass::find($modelId);
        if (! $model) {
            return response()->json(['error' => 'Model not found'], 404);
        }

        // Get the hash record
        $hash = $model->getCurrentHash();
        if (! $hash) {
            return response()->json(['error' => 'Model has no hash record'], 400);
        }

        // Determine which publishers to use
        $publisherIds = $request->input('publisher_ids', []);
        $publisherNames = $request->input('publisher_names', []);

        $query = Publisher::active()->forModel($modelClass);

        if (! empty($publisherIds)) {
            $query->whereIn('id', $publisherIds);
        } elseif (! empty($publisherNames)) {
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
    #[OA\Get(
        path: '/models/{type}/{id}/publishes',
        summary: 'Get publish history for a model',
        tags: ['Publishing'],
        parameters: [
            new OA\Parameter(
                name: 'type',
                in: 'path',
                required: true,
                description: 'Model type',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Model ID',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'status',
                in: 'query',
                required: false,
                description: 'Filter by status',
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['pending', 'dispatched', 'published', 'failed', 'deferred']
                )
            ),
            new OA\Parameter(
                name: 'publisher_id',
                in: 'query',
                required: false,
                description: 'Filter by publisher ID',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Items per page',
                schema: new OA\Schema(type: 'integer', default: 50)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated publish history',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/PaginationMeta'),
                        new OA\Schema(
                            properties: [
                                new OA\Property(
                                    property: 'data',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/Publish')
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Model or hash not found'
            ),
        ]
    )]
    public function getPublishHistory(Request $request, string $modelType, int $modelId): JsonResponse
    {
        $modelClass = $this->resolveModelClass($modelType);

        if (! class_exists($modelClass)) {
            return response()->json(['error' => 'Model type not found'], 404);
        }

        $hash = Hash::where('hashable_type', $modelClass)
            ->where('hashable_id', $modelId)
            ->first();

        if (! $hash) {
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
    #[OA\Get(
        path: '/publishers',
        summary: 'Get all publishers',
        description: 'Retrieve publishers optionally filtered by model type and status',
        tags: ['Publishing'],
        parameters: [
            new OA\Parameter(
                name: 'model_type',
                in: 'query',
                required: false,
                description: 'Filter by model type',
                schema: new OA\Schema(type: 'string'),
                example: 'App\\Models\\User'
            ),
            new OA\Parameter(
                name: 'status',
                in: 'query',
                required: false,
                description: 'Filter by status',
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['active', 'inactive']
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of publishers',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'publishers',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer'),
                                    new OA\Property(property: 'name', type: 'string'),
                                    new OA\Property(property: 'model_type', type: 'string'),
                                    new OA\Property(property: 'publisher_class', type: 'string'),
                                    new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive']),
                                    new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                ]
                            )
                        ),
                    ]
                )
            ),
        ]
    )]
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
    #[OA\Post(
        path: '/detect-changes',
        summary: 'Trigger change detection',
        description: 'Dispatches a job to detect changes for a specific model type or all models',
        tags: ['Hash Management'],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'model_type',
                        type: 'string',
                        description: 'Optional model type to check',
                        example: 'App\\Models\\User'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Detection job dispatched',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Change detection job dispatched'),
                        new OA\Property(property: 'model_type', type: 'string', example: 'App\\Models\\User'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Model type not found'
            ),
        ]
    )]
    public function detectChanges(Request $request): JsonResponse
    {
        $modelType = null;

        if ($request->has('model_type')) {
            $modelType = $this->resolveModelClass($request->input('model_type'));

            if (! class_exists($modelType)) {
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
    #[OA\Get(
        path: '/stats',
        summary: 'Get hash tracking statistics',
        tags: ['Statistics'],
        parameters: [
            new OA\Parameter(
                name: 'model_type',
                in: 'query',
                required: false,
                description: 'Get stats for specific model type',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Statistics data',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'total_hashes', type: 'integer'),
                        new OA\Property(property: 'models_without_dependents', type: 'integer'),
                        new OA\Property(property: 'models_with_dependents', type: 'integer'),
                        new OA\Property(property: 'total_publishers', type: 'integer'),
                        new OA\Property(property: 'active_publishers', type: 'integer'),
                        new OA\Property(
                            property: 'publishes_by_status',
                            type: 'object'
                        ),
                        new OA\Property(
                            property: 'model_specific',
                            type: 'object',
                            nullable: true,
                            properties: [
                                new OA\Property(property: 'model_type', type: 'string'),
                                new OA\Property(property: 'total_models', type: 'integer'),
                                new OA\Property(property: 'publishers', type: 'integer'),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function getStats(Request $request): JsonResponse
    {
        $stats = [
            'total_hashes' => Hash::count(),
            'models_without_dependents' => Hash::whereDoesntHave('dependents')->count(),
            'models_with_dependents' => Hash::whereHas('dependents')->count(),
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
    #[OA\Post(
        path: '/retry-publishes',
        summary: 'Retry failed or deferred publishes',
        tags: ['Publishing'],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'publisher_id',
                        type: 'integer',
                        description: 'Retry only for specific publisher'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Retry jobs dispatched',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Retry jobs dispatched'),
                        new OA\Property(property: 'count', type: 'integer', example: 5),
                    ]
                )
            ),
        ]
    )]
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
    #[OA\Patch(
        path: '/publishers/{id}',
        summary: 'Update publisher status',
        tags: ['Publishing'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Publisher ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(
                        property: 'status',
                        type: 'string',
                        enum: ['active', 'inactive']
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Publisher updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(
                            property: 'publisher',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'status', type: 'string'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Publisher not found'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error'
            ),
        ]
    )]
    public function updatePublisher(Request $request, int $publisherId): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:active,inactive',
        ]);

        $publisher = Publisher::find($publisherId);
        if (! $publisher) {
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
    #[OA\Post(
        path: '/initialize-hashes',
        summary: 'Initialize hashes for models',
        description: 'Creates hash records for models that do not have them',
        tags: ['Hash Management'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['model_type'],
                properties: [
                    new OA\Property(
                        property: 'model_type',
                        type: 'string',
                        description: 'Model type to initialize',
                        example: 'App\\Models\\User'
                    ),
                    new OA\Property(
                        property: 'chunk_size',
                        type: 'integer',
                        description: 'Number of models to process at once',
                        minimum: 1,
                        maximum: 1000,
                        default: 100
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Initialization completed',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Hash initialization completed'),
                        new OA\Property(property: 'model_type', type: 'string'),
                        new OA\Property(property: 'initialized_count', type: 'integer'),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Model does not implement Hashable contract'
            ),
            new OA\Response(
                response: 404,
                description: 'Model type not found'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error'
            ),
        ]
    )]
    public function initializeHashes(Request $request): JsonResponse
    {
        $request->validate([
            'model_type' => 'required|string',
            'chunk_size' => 'integer|min:1|max:1000',
        ]);

        $modelType = $this->resolveModelClass($request->input('model_type'));

        if (! class_exists($modelType)) {
            return response()->json(['error' => 'Model type not found'], 404);
        }

        $model = new $modelType;

        if (! method_exists($model, 'getHashableAttributes')) {
            return response()->json(['error' => 'Model does not implement Hashable contract'], 400);
        }

        $chunkSize = $request->input('chunk_size', 100);
        $initialized = 0;

        // Find models without hashes
        $modelType::query()
            ->whereNotExists(function ($query) use ($modelType) {
                $query->select('id')
                    ->from(config('laravel-hash-change-detector.tables.hashes', 'hashes'))
                    ->whereColumn('hashable_id', (new $modelType)->getTable().'.id')
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
            $fullClass = $namespace.$modelType;
            if (class_exists($fullClass)) {
                return $fullClass;
            }
        }

        // Return as-is if not found
        return $modelType;
    }
}

// OpenAPI Schema Definitions
#[OA\Schema(
    schema: 'Publish',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'hash_id', type: 'integer'),
        new OA\Property(property: 'publisher_id', type: 'integer'),
        new OA\Property(property: 'published_hash', type: 'string'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'dispatched', 'published', 'failed', 'deferred']),
        new OA\Property(property: 'attempts', type: 'integer'),
        new OA\Property(property: 'last_error', type: 'string', nullable: true),
        new OA\Property(property: 'next_try', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'published_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'publisher', ref: '#/components/schemas/Publisher', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'Publisher',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'model_type', type: 'string'),
        new OA\Property(property: 'publisher_class', type: 'string'),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive']),
        new OA\Property(property: 'config', type: 'object', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'PaginationMeta',
    properties: [
        new OA\Property(property: 'current_page', type: 'integer'),
        new OA\Property(property: 'from', type: 'integer', nullable: true),
        new OA\Property(property: 'last_page', type: 'integer'),
        new OA\Property(property: 'per_page', type: 'integer'),
        new OA\Property(property: 'to', type: 'integer', nullable: true),
        new OA\Property(property: 'total', type: 'integer'),
    ]
)]
class HashChangeDetectorApiSchemas {}
