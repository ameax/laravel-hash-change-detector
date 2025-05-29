<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Http\Controllers;

use ameax\HashChangeDetector\Models\Publisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class PublisherController extends Controller
{
    /**
     * Display a listing of publishers.
     * 
     * GET /api/hash-change-detector/publishers
     */
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
            $query->where('name', 'like', '%' . $request->input('search') . '%');
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
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:' . config('laravel-hash-change-detector.tables.publishers', 'publishers') . ',name',
            'model_type' => 'required|string',
            'publisher_class' => 'required|string',
            'status' => 'sometimes|in:active,inactive',
            'config' => 'sometimes|array',
        ]);

        // Validate model class exists
        if (!class_exists($validated['model_type'])) {
            return response()->json([
                'error' => 'Model class does not exist',
                'model_type' => $validated['model_type'],
            ], 422);
        }

        // Validate publisher class exists and implements the interface
        if (!class_exists($validated['publisher_class'])) {
            return response()->json([
                'error' => 'Publisher class does not exist',
                'publisher_class' => $validated['publisher_class'],
            ], 422);
        }

        $publisherInstance = app($validated['publisher_class']);
        if (!$publisherInstance instanceof \ameax\HashChangeDetector\Contracts\Publisher) {
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
    public function show(int $id): JsonResponse
    {
        $publisher = Publisher::with(['publishes' => function ($query) {
            $query->latest()->limit(10);
        }])->find($id);

        if (!$publisher) {
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
    public function update(Request $request, int $id): JsonResponse
    {
        $publisher = Publisher::find($id);
        
        if (!$publisher) {
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
    public function destroy(int $id): JsonResponse
    {
        $publisher = Publisher::find($id);
        
        if (!$publisher) {
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
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'publisher_ids' => 'required|array',
            'publisher_ids.*' => 'integer|exists:' . config('laravel-hash-change-detector.tables.publishers', 'publishers') . ',id',
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
    public function test(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'publisher_id' => 'sometimes|integer|exists:' . config('laravel-hash-change-detector.tables.publishers', 'publishers') . ',id',
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
                if (!class_exists($validated['publisher_class'])) {
                    return response()->json(['error' => 'Publisher class not found'], 422);
                }
                $publisherInstance = app($validated['publisher_class']);
                $modelType = $validated['model_type'];
            }

            // Get the model
            if (!class_exists($modelType)) {
                return response()->json(['error' => 'Model type not found'], 422);
            }

            $model = $modelType::find($validated['model_id']);
            if (!$model) {
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
    public function stats(Request $request, int $id): JsonResponse
    {
        $publisher = Publisher::find($id);
        
        if (!$publisher) {
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