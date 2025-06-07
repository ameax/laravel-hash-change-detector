<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Http\Controllers;

use ameax\HashChangeDetector\Models\Publisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Publisher Management',
    description: 'CRUD operations for managing publishers'
)]
class PublisherController extends Controller
{
    /**
     * Display a listing of publishers.
     *
     * GET /api/hash-change-detector/publishers
     */
    #[OA\Get(
        path: '/publishers',
        summary: 'List all publishers with pagination and filtering',
        tags: ['Publisher Management'],
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
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Search by name',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'sort_by',
                in: 'query',
                required: false,
                description: 'Sort field',
                schema: new OA\Schema(
                    type: 'string',
                    default: 'created_at',
                    enum: ['id', 'name', 'created_at', 'updated_at', 'status']
                )
            ),
            new OA\Parameter(
                name: 'sort_direction',
                in: 'query',
                required: false,
                description: 'Sort direction',
                schema: new OA\Schema(
                    type: 'string',
                    default: 'desc',
                    enum: ['asc', 'desc']
                )
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Items per page',
                schema: new OA\Schema(
                    type: 'integer',
                    default: 15,
                    minimum: 1,
                    maximum: 100
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of publishers',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/PaginationMeta'),
                        new OA\Schema(
                            properties: [
                                new OA\Property(
                                    property: 'data',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/Publisher')
                                ),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Publisher::query();

        // Filter by model type
        if ($request->has('model_type')) {
            $query->where('model_type', $request->input('model_type'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        // Pagination
        $publishers = $query->paginate($request->input('per_page', 15));

        return response()->json($publishers);
    }

    /**
     * Store a newly created publisher.
     *
     * POST /api/hash-change-detector/publishers
     */
    #[OA\Post(
        path: '/publishers',
        summary: 'Create a new publisher',
        tags: ['Publisher Management'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'model_type', 'publisher_class'],
                properties: [
                    new OA\Property(
                        property: 'name',
                        type: 'string',
                        description: 'Unique name for the publisher',
                        maxLength: 255,
                        example: 'user-api-publisher'
                    ),
                    new OA\Property(
                        property: 'model_type',
                        type: 'string',
                        description: 'Fully qualified class name of the model',
                        example: 'App\\Models\\User'
                    ),
                    new OA\Property(
                        property: 'publisher_class',
                        type: 'string',
                        description: 'Fully qualified class name of the publisher',
                        example: 'App\\Publishers\\UserApiPublisher'
                    ),
                    new OA\Property(
                        property: 'status',
                        type: 'string',
                        description: 'Publisher status',
                        enum: ['active', 'inactive'],
                        default: 'active'
                    ),
                    new OA\Property(
                        property: 'config',
                        type: 'object',
                        description: 'Optional configuration for the publisher',
                        example: ['endpoint' => 'https://api.example.com/webhook', 'timeout' => 30]
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Publisher created successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/Publisher')
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string'),
                        new OA\Property(property: 'model_type', type: 'string', nullable: true),
                        new OA\Property(property: 'publisher_class', type: 'string', nullable: true),
                    ]
                )
            ),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:'.config('laravel-hash-change-detector.tables.publishers', 'publishers').',name',
            'model_type' => 'required|string',
            'publisher_class' => 'required|string',
            'status' => 'sometimes|in:active,inactive',
            'config' => 'sometimes|array',
        ]);

        // Validate model class exists
        if (! class_exists($validated['model_type'])) {
            return response()->json([
                'error' => 'Model class does not exist',
                'model_type' => $validated['model_type'],
            ], 422);
        }

        // Validate publisher class exists and implements the interface
        if (! class_exists($validated['publisher_class'])) {
            return response()->json([
                'error' => 'Publisher class does not exist',
                'publisher_class' => $validated['publisher_class'],
            ], 422);
        }

        $publisherInstance = app($validated['publisher_class']);
        if (! $publisherInstance instanceof \ameax\HashChangeDetector\Contracts\Publisher) {
            return response()->json([
                'error' => 'Publisher class must implement Publisher contract',
                'publisher_class' => $validated['publisher_class'],
            ], 422);
        }

        // Create the publisher
        $publisher = Publisher::create([
            'name' => $validated['name'],
            'model_type' => $validated['model_type'],
            'publisher_class' => $validated['publisher_class'],
            'status' => $validated['status'] ?? 'active',
            'config' => $validated['config'] ?? null,
        ]);

        return response()->json($publisher, 201);
    }

    /**
     * Display the specified publisher.
     *
     * GET /api/hash-change-detector/publishers/{id}
     */
    #[OA\Get(
        path: '/publishers/{id}',
        summary: 'Get a specific publisher with statistics',
        tags: ['Publisher Management'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Publisher ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Publisher details with statistics',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'publisher',
                            allOf: [
                                new OA\Schema(ref: '#/components/schemas/Publisher'),
                                new OA\Schema(
                                    properties: [
                                        new OA\Property(
                                            property: 'publishes',
                                            type: 'array',
                                            description: 'Recent publishes (limited to 10)',
                                            items: new OA\Items(ref: '#/components/schemas/Publish')
                                        ),
                                    ]
                                ),
                            ]
                        ),
                        new OA\Property(
                            property: 'stats',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total_publishes', type: 'integer'),
                                new OA\Property(property: 'published', type: 'integer'),
                                new OA\Property(property: 'failed', type: 'integer'),
                                new OA\Property(property: 'pending', type: 'integer'),
                                new OA\Property(property: 'deferred', type: 'integer'),
                                new OA\Property(property: 'last_published_at', type: 'string', format: 'date-time', nullable: true),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Publisher not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Publisher not found'),
                    ]
                )
            ),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $publisher = Publisher::with(['publishes' => function ($query) {
            $query->latest()->limit(10);
        }])->find($id);

        if (! $publisher) {
            return response()->json(['error' => 'Publisher not found'], 404);
        }

        // Add statistics
        $stats = [
            'total_publishes' => $publisher->publishes()->count(),
            'published' => $publisher->publishes()->where('status', 'published')->count(),
            'failed' => $publisher->publishes()->where('status', 'failed')->count(),
            'pending' => $publisher->publishes()->where('status', 'pending')->count(),
            'deferred' => $publisher->publishes()->where('status', 'deferred')->count(),
            'last_published_at' => $publisher->publishes()
                ->where('status', 'published')
                ->latest('published_at')
                ->value('published_at'),
        ];

        return response()->json([
            'publisher' => $publisher,
            'stats' => $stats,
        ]);
    }

    /**
     * Update the specified publisher.
     *
     * PUT/PATCH /api/hash-change-detector/publishers/{id}
     */
    #[OA\Put(
        path: '/publishers/{id}',
        summary: 'Update a publisher',
        tags: ['Publisher Management'],
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
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'name',
                        type: 'string',
                        description: 'Publisher name',
                        maxLength: 255
                    ),
                    new OA\Property(
                        property: 'status',
                        type: 'string',
                        description: 'Publisher status',
                        enum: ['active', 'inactive']
                    ),
                    new OA\Property(
                        property: 'config',
                        type: 'object',
                        nullable: true,
                        description: 'Publisher configuration'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Publisher updated successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/Publisher')
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
    #[OA\Patch(
        path: '/publishers/{id}',
        summary: 'Partially update a publisher',
        tags: ['Publisher Management'],
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
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive']),
                    new OA\Property(property: 'config', type: 'object', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Publisher updated'),
            new OA\Response(response: 404, description: 'Publisher not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $publisher = Publisher::find($id);

        if (! $publisher) {
            return response()->json(['error' => 'Publisher not found'], 404);
        }

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique(config('laravel-hash-change-detector.tables.publishers', 'publishers'))
                    ->ignore($publisher->id),
            ],
            'status' => 'sometimes|in:active,inactive',
            'config' => 'sometimes|nullable|array',
        ]);

        $publisher->update($validated);

        return response()->json($publisher);
    }

    /**
     * Remove the specified publisher.
     *
     * DELETE /api/hash-change-detector/publishers/{id}
     */
    #[OA\Delete(
        path: '/publishers/{id}',
        summary: 'Delete a publisher',
        description: 'Deletes a publisher if it has no pending publishes',
        tags: ['Publisher Management'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Publisher ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Publisher deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Publisher deleted successfully'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Publisher not found'
            ),
            new OA\Response(
                response: 409,
                description: 'Cannot delete publisher with pending publishes',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string'),
                        new OA\Property(property: 'pending_count', type: 'integer'),
                    ]
                )
            ),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $publisher = Publisher::find($id);

        if (! $publisher) {
            return response()->json(['error' => 'Publisher not found'], 404);
        }

        // Check if there are any pending publishes
        $pendingCount = $publisher->publishes()
            ->whereIn('status', ['pending', 'dispatched'])
            ->count();

        if ($pendingCount > 0) {
            return response()->json([
                'error' => 'Cannot delete publisher with pending publishes',
                'pending_count' => $pendingCount,
            ], 409);
        }

        // Delete the publisher (publishes will be cascade deleted)
        $publisher->delete();

        return response()->json(['message' => 'Publisher deleted successfully']);
    }

    /**
     * Bulk update publishers.
     *
     * PATCH /api/hash-change-detector/publishers/bulk
     */
    #[OA\Patch(
        path: '/publishers/bulk',
        summary: 'Bulk update publisher status',
        tags: ['Publisher Management'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['publisher_ids', 'status'],
                properties: [
                    new OA\Property(
                        property: 'publisher_ids',
                        type: 'array',
                        description: 'Array of publisher IDs to update',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2, 3]
                    ),
                    new OA\Property(
                        property: 'status',
                        type: 'string',
                        description: 'New status for all publishers',
                        enum: ['active', 'inactive']
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Publishers updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'updated_count', type: 'integer'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error'
            ),
        ]
    )]
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'publisher_ids' => 'required|array',
            'publisher_ids.*' => 'integer|exists:'.config('laravel-hash-change-detector.tables.publishers', 'publishers').',id',
            'status' => 'required|in:active,inactive',
        ]);

        $updated = Publisher::whereIn('id', $validated['publisher_ids'])
            ->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Publishers updated successfully',
            'updated_count' => $updated,
        ]);
    }

    /**
     * Get publisher types (available publisher classes).
     *
     * GET /api/hash-change-detector/publishers/types
     */
    #[OA\Get(
        path: '/publishers/types',
        summary: 'Get available publisher types',
        description: 'Returns built-in and custom registered publisher types',
        tags: ['Publisher Management'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of available publisher types',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'types',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'class', type: 'string'),
                                    new OA\Property(property: 'name', type: 'string'),
                                    new OA\Property(property: 'description', type: 'string'),
                                    new OA\Property(property: 'abstract', type: 'boolean', nullable: true),
                                ]
                            )
                        ),
                    ]
                )
            ),
        ]
    )]
    public function getTypes(): JsonResponse
    {
        // Get built-in publishers
        $types = [
            [
                'class' => \ameax\HashChangeDetector\Publishers\LogPublisher::class,
                'name' => 'Log Publisher',
                'description' => 'Logs model changes to Laravel log',
            ],
            [
                'class' => \ameax\HashChangeDetector\Publishers\HttpPublisher::class,
                'name' => 'HTTP Publisher',
                'description' => 'Sends model changes via HTTP request',
                'abstract' => true,
            ],
        ];

        // Allow apps to register custom publisher types
        $customTypes = config('laravel-hash-change-detector.publisher_types', []);
        $types = array_merge($types, $customTypes);

        return response()->json(['types' => $types]);
    }

    /**
     * Test a publisher configuration.
     *
     * POST /api/hash-change-detector/publishers/test
     */
    #[OA\Post(
        path: '/publishers/test',
        summary: 'Test a publisher configuration',
        description: 'Tests a publisher without actually publishing data',
        tags: ['Publisher Management'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['model_id'],
                oneOf: [
                    new OA\Schema(
                        required: ['publisher_id', 'model_id'],
                        properties: [
                            new OA\Property(property: 'publisher_id', type: 'integer'),
                            new OA\Property(property: 'model_id', type: 'integer'),
                            new OA\Property(property: 'config', type: 'object'),
                        ]
                    ),
                    new OA\Schema(
                        required: ['publisher_class', 'model_type', 'model_id'],
                        properties: [
                            new OA\Property(property: 'publisher_class', type: 'string'),
                            new OA\Property(property: 'model_type', type: 'string'),
                            new OA\Property(property: 'model_id', type: 'integer'),
                            new OA\Property(property: 'config', type: 'object'),
                        ]
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Test completed successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(
                            property: 'result',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'should_publish', type: 'boolean'),
                                new OA\Property(property: 'data_preview', type: 'object'),
                                new OA\Property(property: 'publisher_class', type: 'string'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Model not found'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error'
            ),
            new OA\Response(
                response: 500,
                description: 'Test failed',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string'),
                        new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            ),
        ]
    )]
    public function test(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'publisher_id' => 'sometimes|integer|exists:'.config('laravel-hash-change-detector.tables.publishers', 'publishers').',id',
            'publisher_class' => 'required_without:publisher_id|string',
            'model_type' => 'required_without:publisher_id|string',
            'model_id' => 'required|integer',
            'config' => 'sometimes|array',
        ]);

        try {
            // Get or create publisher instance
            if (isset($validated['publisher_id'])) {
                $publisher = Publisher::find($validated['publisher_id']);
                $publisherInstance = $publisher->getPublisherInstance();
                $modelType = $publisher->model_type;
            } else {
                if (! class_exists($validated['publisher_class'])) {
                    return response()->json(['error' => 'Publisher class not found'], 422);
                }
                $publisherInstance = app($validated['publisher_class']);
                $modelType = $validated['model_type'];
            }

            // Get the model
            if (! class_exists($modelType)) {
                return response()->json(['error' => 'Model type not found'], 422);
            }

            $model = $modelType::find($validated['model_id']);
            if (! $model) {
                return response()->json(['error' => 'Model not found'], 404);
            }

            // Test if model should be published
            $shouldPublish = $publisherInstance->shouldPublish($model);

            // Get the data that would be sent
            $data = $publisherInstance->getData($model);

            // Try a dry run (don't actually publish)
            $testResult = [
                'should_publish' => $shouldPublish,
                'data_preview' => $data,
                'publisher_class' => get_class($publisherInstance),
            ];

            return response()->json([
                'message' => 'Publisher test completed',
                'result' => $testResult,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Publisher test failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get publish statistics for a publisher.
     *
     * GET /api/hash-change-detector/publishers/{id}/stats
     */
    #[OA\Get(
        path: '/publishers/{id}/stats',
        summary: 'Get publisher statistics',
        description: 'Returns publishing statistics for a specific publisher',
        tags: ['Publisher Management', 'Statistics'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Publisher ID',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'period',
                in: 'query',
                required: false,
                description: 'Time period for statistics',
                schema: new OA\Schema(
                    type: 'string',
                    default: '7days',
                    enum: ['7days', '30days', 'all']
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Publisher statistics',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'publisher',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'status', type: 'string'),
                            ]
                        ),
                        new OA\Property(property: 'period', type: 'string'),
                        new OA\Property(
                            property: 'stats',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'by_status',
                                    type: 'object',
                                    example: ['published' => 100, 'failed' => 5, 'pending' => 2]
                                ),
                                new OA\Property(
                                    property: 'by_day',
                                    type: 'object'
                                ),
                                new OA\Property(property: 'avg_processing_seconds', type: 'number', nullable: true),
                                new OA\Property(property: 'success_rate', type: 'number', format: 'float'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Publisher not found'
            ),
        ]
    )]
    public function stats(Request $request, int $id): JsonResponse
    {
        $publisher = Publisher::find($id);

        if (! $publisher) {
            return response()->json(['error' => 'Publisher not found'], 404);
        }

        $period = $request->input('period', '7days'); // 7days, 30days, all

        $query = $publisher->publishes();

        switch ($period) {
            case '7days':
                $query->where('created_at', '>=', now()->subDays(7));
                break;
            case '30days':
                $query->where('created_at', '>=', now()->subDays(30));
                break;
        }

        // Get stats by status
        $statusStats = $query->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Get daily stats for chart
        $dailyStats = $publisher->publishes()
            ->selectRaw('DATE(created_at) as date, status, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays($period === '7days' ? 7 : 30))
            ->groupBy('date', 'status')
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(function ($day) {
                return $day->pluck('count', 'status')->toArray();
            });

        // Average processing time
        $avgProcessingTime = null;
        if (config('database.default') === 'mysql') {
            $avgProcessingTime = $publisher->publishes()
                ->where('status', 'published')
                ->whereNotNull('published_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, published_at)) as avg_seconds')
                ->value('avg_seconds');
        } else {
            // SQLite compatible version
            $avgProcessingTime = $publisher->publishes()
                ->where('status', 'published')
                ->whereNotNull('published_at')
                ->selectRaw('AVG(CAST((julianday(published_at) - julianday(created_at)) * 86400 AS INTEGER)) as avg_seconds')
                ->value('avg_seconds');
        }

        return response()->json([
            'publisher' => [
                'id' => $publisher->id,
                'name' => $publisher->name,
                'status' => $publisher->status,
            ],
            'period' => $period,
            'stats' => [
                'by_status' => $statusStats,
                'by_day' => $dailyStats,
                'avg_processing_seconds' => $avgProcessingTime,
                'success_rate' => isset($statusStats['published'])
                    ? round(($statusStats['published'] / array_sum($statusStats)) * 100, 2)
                    : 0,
            ],
        ]);
    }
}
