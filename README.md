# Laravel Hash Change Detector

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ameax/laravel-hash-change-detector.svg?style=flat-square)](https://packagist.org/packages/ameax/laravel-hash-change-detector)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/ameax/laravel-hash-change-detector/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/ameax/laravel-hash-change-detector/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/ameax/laravel-hash-change-detector/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/ameax/laravel-hash-change-detector/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/ameax/laravel-hash-change-detector.svg?style=flat-square)](https://packagist.org/packages/ameax/laravel-hash-change-detector)

Detect changes in your Laravel models through hash-based tracking and automatically publish updates to external systems. Perfect for maintaining data synchronization across multiple platforms, APIs, or services.

**Key Features:**
- 🔄 **Two-way sync** for regular Eloquent models (Laravel + external changes)
- 👁️ **One-way tracking** for read-only models (database views, external tables)
- 🔍 **Direct database detection** for changes made outside Laravel
- 📤 **Automatic publishing** to external systems when changes are detected
- 🔗 **Relationship tracking** with parent-child hash propagation

## Table of Contents

- [Installation](#installation)
- [Quick Start](#quick-start)
- [Basic Usage](#basic-usage)
  - [Making a Model Hashable](#making-a-model-hashable)
  - [Tracking Related Models](#tracking-related-models)
- [Model Types and Detection Strategies](#model-types-and-detection-strategies)
- [Direct Database Detection](#direct-database-detection)
- [Publishing System](#publishing-system)
- [Advanced Usage](#advanced-usage)
- [Commands](#commands)
- [Testing](#testing)

## Installation

```bash
composer require ameax/laravel-hash-change-detector
```

Publish and run migrations:

```bash
php artisan vendor:publish --tag="hash-change-detector-migrations"
php artisan migrate
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag="hash-change-detector-config"
```

### Optional: API Documentation with Swagger

If you want to use the included API controllers with automatic Swagger documentation:

```bash
composer require zircote/swagger-php
```

The API controllers include comprehensive OpenAPI attributes for automatic documentation generation with l5-swagger. See [Swagger Integration Guide](docs/swagger-integration.md) for details.

## Quick Start

### 1. Make Your Model Hashable

```php
use ameax\HashChangeDetector\Contracts\Hashable;
use ameax\HashChangeDetector\Traits\InteractsWithHashes;

class Product extends Model implements Hashable
{
    use InteractsWithHashes;
    
    public function getHashableAttributes(): array
    {
        return ['name', 'price', 'sku'];
    }
    
    public function getHashCompositeDependencies(): array
    {
        return []; // No related models to track
    }
    
    public function getHashRelationsToNotifyOnChange(): array
    {
        return []; // No dependent models to notify
    }
}
```

That's it! Your model now automatically:
- Creates a hash when saved
- Updates the hash when attributes change
- Triggers events when changes are detected

## Basic Usage

### Making a Model Hashable

To track changes in a model, implement the `Hashable` interface and use the `InteractsWithHashes` trait:

```php
use ameax\HashChangeDetector\Contracts\Hashable;
use ameax\HashChangeDetector\Traits\InteractsWithHashes;

class Order extends Model implements Hashable
{
    use InteractsWithHashes;
    
    /**
     * Define which attributes to include in the hash
     */
    public function getHashableAttributes(): array
    {
        return [
            'order_number',
            'total_amount', 
            'status',
            'customer_email'
        ];
    }
    
    /**
     * Define which relationships to include in composite hash
     */
    public function getHashCompositeDependencies(): array
    {
        return [
            'orderItems',     // HasMany relationship
            'shipping',       // HasOne relationship
        ];
    }
    
    /**
     * Define which related models should be notified when this changes
     */
    public function getHashRelationsToNotifyOnChange(): array
    {
        return []; // Orders typically don't notify other models
    }
    
    // Your regular model relationships
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
    
    public function shipping()
    {
        return $this->hasOne(ShippingDetail::class);
    }
}
```

### Tracking Related Models

When you have parent-child relationships, the child models should also be hashable and define their parent relationships:

```php
class OrderItem extends Model implements Hashable
{
    use InteractsWithHashes;
    
    public function getHashableAttributes(): array
    {
        return ['product_name', 'quantity', 'price'];
    }
    
    public function getHashCompositeDependencies(): array
    {
        return []; // Child models typically don't track relations
    }
    
    /**
     * Define which parent models should be notified of changes
     */
    public function getHashRelationsToNotifyOnChange(): array
    {
        return ['order']; // Notify parent order when this item changes
    }
    
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
```

### How It Works

1. **Individual Hashes**: Each model has its own hash based on `getHashableAttributes()`
2. **Composite Hashes**: Parent models also have a composite hash that includes hashes from all tracked relations (defined in `getHashCompositeDependencies()`)
3. **Automatic Updates**: When a model changes, it notifies related models defined in `getHashRelationsToNotifyOnChange()` to recalculate their hashes
4. **Collection Support**: Automatically handles HasMany and BelongsToMany relationships, updating all models in the collection
5. **Event Driven**: All updates trigger events that you can listen to

### Understanding the Two Relationship Methods

The package uses two methods to define relationships:

#### `getHashCompositeDependencies()`
Defines which related models should be included when calculating this model's composite hash.
- Used to track dependencies that affect this model's state
- Example: An Order includes its OrderItems in its composite hash

#### `getHashRelationsToNotifyOnChange()`
Defines which related models should be notified when this model changes.
- Used to propagate changes up the relationship chain
- Supports both single models (BelongsTo, HasOne) and collections (HasMany, BelongsToMany)
- Example: When an OrderItem changes, it notifies its parent Order

```php
// Example: Blog system with User -> Posts -> Comments
class User extends Model implements Hashable
{
    use InteractsWithHashes;
    
    public function getHashCompositeDependencies(): array
    {
        return ['posts']; // User's hash includes all their posts
    }
    
    public function getHashRelationsToNotifyOnChange(): array
    {
        return []; // Users typically don't notify other models
    }
}

class Post extends Model implements Hashable
{
    use InteractsWithHashes;
    
    public function getHashCompositeDependencies(): array
    {
        return ['comments', 'user']; // Post's hash includes comments and author
    }
    
    public function getHashRelationsToNotifyOnChange(): array
    {
        return ['user']; // When post changes, notify the author
    }
}

class Comment extends Model implements Hashable
{
    use InteractsWithHashes;
    
    public function getHashCompositeDependencies(): array
    {
        return ['user']; // Comment's hash includes the commenter
    }
    
    public function getHashRelationsToNotifyOnChange(): array
    {
        return ['post', 'post.user']; // Notify both post and post's author
    }
}
```

## Model Types and Detection Strategies

The package supports two types of models, each with different use cases:

### 1. Regular Models (Two-Way Sync)

Standard Eloquent models that can be modified both through Laravel AND external systems:

```php
class Product extends Model implements Hashable
{
    use InteractsWithHashes;
    
    protected $fillable = ['name', 'price', 'sku', 'stock'];
    
    public function getHashableAttributes(): array
    {
        return ['name', 'price', 'sku', 'stock'];
    }
}
```

**When to use:**
- Models primarily managed through Laravel but occasionally updated externally
- E-commerce products (admin panel + inventory systems)
- User profiles (app + customer service tools)
- Orders (website + imports from other systems)

**Features:**
- ✅ Full Eloquent functionality (create, update, delete)
- ✅ Automatic hash updates via model events
- ✅ Direct database detection for external changes
- ✅ Can track relationships

### 2. Read-Only Models (One-Way Sync)

Models that are NEVER modified through Laravel, only tracked for external changes:

```php
class SalesReport extends Model implements Hashable
{
    use TracksHashesOnly; // Note: Different trait!
    
    protected $table = 'sales_summary_view'; // Often a database view
    
    public function getHashableAttributes(): array
    {
        return ['report_date', 'total_sales', 'order_count'];
    }
    
    // Prevent accidental modifications
    public function save(array $options = [])
    {
        throw new \RuntimeException('This is a read-only model');
    }
}
```

**When to use:**
- Database views
- External system tables (shared databases)
- Analytics/reporting tables (populated by ETL)
- Legacy tables you shouldn't modify
- Tables updated by database triggers/procedures

**Features:**
- ✅ Read operations via Eloquent
- ❌ No write operations (blocked)
- ❌ No model event overhead
- ✅ Direct database detection only
- ✅ Better performance for large datasets

### Choosing the Right Approach

| Scenario | Model Type | Why |
|----------|------------|-----|
| Products with admin panel | Regular + Detection | Need Eloquent updates + external sync |
| Database view of sales | Read-Only + Detection | Can't update views via Eloquent |
| User accounts | Regular + Detection | App updates + admin tools |
| External inventory table | Read-Only + Detection | Managed by warehouse system |
| Orders with API imports | Regular + Detection | Create via app + import via API |
| Analytics aggregates | Read-Only + Detection | Updated by SQL procedures |

## Direct Database Detection

Direct database detection finds changes made outside of Laravel (SQL updates, triggers, external apps, etc.).

### Setting Up Detection

Add to your scheduler in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Detect all changes every 5 minutes
    $schedule->command('hash-detector:detect-changes')
        ->everyFiveMinutes();
        
    // Or detect changes for specific models
    $schedule->command('hash-detector:detect-changes', ['model' => Order::class])
        ->everyFiveMinutes();
}
```

### How Direct Detection Works

1. **Calculates hashes in the database** using SQL functions
2. **Compares with stored hashes** to find changes
3. **Updates changed records** and triggers events
4. **Detects deletions** by finding orphaned hash records

### Deletion Detection

The package automatically detects when models are deleted:

#### Immediate Detection (Eloquent Deletions)
When models are deleted through Eloquent (`$model->delete()`), deletion publishers are triggered immediately:
- The `HashableModelDeleted` event fires before the hash is removed
- All configured DeletePublishers are notified instantly
- No need to wait for scheduled detection

#### Scheduled Detection (Direct Database Deletions)
For deletions made outside Laravel (`DELETE FROM orders WHERE id = 123`):
- The scheduled `detect-changes` command finds orphaned hash records
- Updates all dependent models that referenced the deleted model
- Triggers deletion events for configured DeletePublishers
- Cleans up orphaned hash and publish records

Both methods ensure your external systems are notified when data is removed.

### Example Scenario

```sql
-- Someone updates order directly in database
UPDATE orders SET total_amount = 150.00 WHERE id = 123;

-- Next detection run will:
-- 1. Calculate new hash for order 123
-- 2. Compare with stored hash
-- 3. Update the hash record
-- 4. Trigger publishing if configured
```

## Publishing System

Automatically sync changes to external systems by creating publishers:

### Creating Publishers for Create/Update Operations

```php
use ameax\HashChangeDetector\Contracts\Publisher;

class OrderApiPublisher implements Publisher
{
    public function publish(Model $model, array $data): bool
    {
        $response = Http::post('https://api.example.com/orders', [
            'order_id' => $model->id,
            'data' => $data,
        ]);
        
        return $response->successful();
    }
    
    public function getData(Model $model): array
    {
        return [
            'order' => $model->toArray(),
            'items' => $model->orderItems->toArray(),
            'shipping' => $model->shipping?->toArray(),
        ];
    }
}
```

### Creating Publishers for Delete Operations

For handling model deletions, implement the `DeletePublisher` interface:

```php
use ameax\HashChangeDetector\Contracts\DeletePublisher;

class OrderApiDeletePublisher implements DeletePublisher
{
    public function publishDeletion(string $modelClass, int $modelId, array $lastKnownData): bool
    {
        $response = Http::delete("https://api.example.com/orders/{$modelId}");
        
        return $response->successful();
    }
    
    public function shouldPublishDeletion(string $modelClass, int $modelId): bool
    {
        // Optional: Add logic to skip certain deletions
        return true;
    }
    
    public function getMaxAttempts(): int
    {
        return 3;
    }
}
```

### Combined Publisher (Handles Both Operations)

You can create a publisher that handles both create/update and delete operations:

```php
use ameax\HashChangeDetector\Contracts\Publisher;
use ameax\HashChangeDetector\Contracts\DeletePublisher;

class OrderApiFullPublisher implements Publisher, DeletePublisher
{
    // Create/Update methods
    public function publish(Model $model, array $data): bool
    {
        $method = $model->wasRecentlyCreated ? 'post' : 'put';
        $response = Http::$method('https://api.example.com/orders', [
            'order_id' => $model->id,
            'data' => $data,
        ]);
        
        return $response->successful();
    }
    
    public function getData(Model $model): array
    {
        return $model->toArray();
    }
    
    // Delete methods
    public function publishDeletion(string $modelClass, int $modelId, array $lastKnownData): bool
    {
        $response = Http::delete("https://api.example.com/orders/{$modelId}");
        
        return $response->successful();
    }
    
    public function shouldPublishDeletion(string $modelClass, int $modelId): bool
    {
        return true;
    }
    
    // Shared methods
    public function shouldPublish(Model $model): bool
    {
        return $model->status !== 'draft';
    }
    
    public function getMaxAttempts(): int
    {
        return 3;
    }
}
```

### Registering Publishers

In a service provider:

```php
use ameax\HashChangeDetector\Facades\HashChangeDetector;

HashChangeDetector::registerPublisher(
    'order-api',
    Order::class,
    OrderApiPublisher::class
);
```

Or via command:

```bash
php artisan hash-detector:publisher:create "Order API" Order OrderApiPublisher
```

### Retry Failed Publishes

Add to your scheduler:

```php
$schedule->command('hash-detector:retry-publishes')
    ->everyFiveMinutes();
```

## Advanced Usage

### Working with Read-Only Models

For read-only models (see [Model Types](#model-types-and-detection-strategies) above), you need to initialize hashes since there are no Eloquent events:

```bash
# Initialize hashes for existing records
php artisan hash-detector:initialize-hashes "App\Models\SalesReport"

# Process in chunks for large tables
php artisan hash-detector:initialize-hashes "App\Models\SalesReport" --chunk=1000
```

Then use normal detection:

```php
$schedule->command('hash-detector:detect-changes', ['model' => SalesReport::class])
    ->hourly(); // Can use different frequency than regular models
```

### Mixed Model Environment

You can use both model types in the same application:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Regular models - frequent checks
    $schedule->command('hash-detector:detect-changes', ['model' => Product::class])
        ->everyFiveMinutes();
        
    $schedule->command('hash-detector:detect-changes', ['model' => Order::class])
        ->everyFiveMinutes();
    
    // Read-only models - less frequent checks
    $schedule->command('hash-detector:detect-changes', ['model' => WarehouseInventory::class])
        ->everyThirtyMinutes();
        
    $schedule->command('hash-detector:detect-changes', ['model' => SalesAnalytics::class])
        ->hourly();
}
```

### Nested Relations

Track nested relationships using dot notation:

```php
public function getHashCompositeDependencies(): array
{
    return [
        'orderItems',           // Direct relation
        'orderItems.product',   // Nested relation
    ];
}

// Notify nested parent models
public function getHashRelationsToNotifyOnChange(): array
{
    return [
        'user',              // Direct parent
        'user.country',      // Nested parent
    ];
}
```

### Multiple Parents & Collections

A model can notify multiple parent models, including collections:

```php
// In a Comment model that belongs to both Post and User
public function getHashRelationsToNotifyOnChange(): array
{
    return [
        'post',      // BelongsTo - single model
        'user',      // BelongsTo - single model
    ];
}

// In a User model with many posts
public function getHashRelationsToNotifyOnChange(): array
{
    return [
        'posts',     // HasMany - collection of models
    ];
}
```

The package automatically handles both single models and collections, updating all dependent models when changes occur.

### Circular Dependency Protection

The package includes built-in protection against infinite loops in circular relationships:

```php
// This scenario is automatically handled without infinite loops
class User extends Model implements Hashable
{
    use InteractsWithHashes;
    
    public function getHashRelationsToNotifyOnChange(): array
    {
        return ['posts']; // When user changes, notify all posts
    }
}

class Post extends Model implements Hashable
{
    use InteractsWithHashes;
    
    public function getHashRelationsToNotifyOnChange(): array
    {
        return ['user']; // When post changes, notify user
    }
}
```

**Protection Mechanisms:**
1. **Cycle Detection**: Models currently being processed are tracked to prevent re-entry
2. **Depth Limiting**: Maximum update chain depth of 10 levels
3. **Automatic Recovery**: Processing stack is cleared after each update chain

**Best Practices:**
- Design your relationships to minimize circular dependencies
- Use one-way notifications where possible (child → parent)
- Consider using composite dependencies instead of bidirectional notifications

### Custom Hash Algorithm

In your config file:

```php
'hash_algorithm' => 'sha256', // Default is 'md5'
```

### Syncing from External APIs

When receiving data from external APIs, you can update models without triggering publishers:

```php
use ameax\HashChangeDetector\Traits\SyncsFromExternalSources;

class Product extends Model implements Hashable
{
    use InteractsWithHashes, SyncsFromExternalSources;
    
    // ... your model configuration
}

// Sync single model from API without triggering publishers
$product->syncFromExternal([
    'name' => 'Updated from API',
    'price' => 99.99,
    'stock' => 50
], 'external-api'); // Mark 'external-api' publisher as synced

// Create or update from API
$product = Product::syncOrCreateFromExternal(
    ['sku' => 'WIDGET-001'], // Find by SKU
    [
        'name' => 'Widget',
        'price' => 49.99,
        'stock' => 100
    ],
    'external-api' // Optional: specific publisher to mark as synced
);

// Bulk sync from API
$products = Product::bulkSyncFromExternal($apiData, 'sku', 'external-api');

// Manual approach without trait
$product->fill($apiData);
$product->saveQuietly(); // Laravel's quiet save
$product->updateHashWithoutPublishing(['external-api', 'another-api']);
```

**Key Features:**
- Updates hash to reflect current state
- Marks specified publishers as "synced" without triggering them
- Prevents infinite sync loops between systems
- Fires `HashUpdatedWithoutPublishing` event instead of `HashChanged`

### Handling Deletions

Listen for deletion events:

```php
use ameax\HashChangeDetector\Events\HashableModelDeleted;

class HandleDeletedModel
{
    public function handle(HashableModelDeleted $event)
    {
        Log::info("Model deleted: {$event->modelClass} ID: {$event->modelId}");
        
        // Notify external systems
        // Clean up related data
        // etc.
    }
}
```

## Commands

```bash
# Detect changes in all models
php artisan hash-detector:detect-changes

# Detect changes in specific model
php artisan hash-detector:detect-changes "App\Models\Order"

# Initialize hashes for read-only models
php artisan hash-detector:initialize-hashes "App\Models\ReportSummary"
php artisan hash-detector:initialize-hashes "App\Models\ReportSummary" --chunk=1000

# Retry failed publishes
php artisan hash-detector:retry-publishes

# List publishers
php artisan hash-detector:publisher:list

# Toggle publisher status
php artisan hash-detector:publisher:toggle {id} --activate
php artisan hash-detector:publisher:toggle {id} --deactivate
```

## Complete Example

Here's a full example with Order and OrderItem models:

```php
// app/Models/Order.php
class Order extends Model implements Hashable
{
    use InteractsWithHashes;
    
    protected $fillable = ['order_number', 'customer_email', 'total_amount', 'status'];
    
    public function getHashableAttributes(): array
    {
        return ['order_number', 'customer_email', 'total_amount', 'status'];
    }
    
    public function getHashCompositeDependencies(): array
    {
        return ['items'];
    }
    
    public function getHashRelationsToNotifyOnChange(): array
    {
        return []; // Orders don't notify other models
    }
    
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}

// app/Models/OrderItem.php
class OrderItem extends Model implements Hashable
{
    use InteractsWithHashes;
    
    protected $fillable = ['order_id', 'product_name', 'quantity', 'price'];
    
    public function getHashableAttributes(): array
    {
        return ['product_name', 'quantity', 'price'];
    }
    
    public function getHashCompositeDependencies(): array
    {
        return [];
    }
    
    public function getHashRelationsToNotifyOnChange(): array
    {
        return ['order']; // Notify parent order
    }
    
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}

// Usage
$order = Order::create([
    'order_number' => 'ORD-001',
    'customer_email' => 'customer@example.com',
    'total_amount' => 100.00,
    'status' => 'pending'
]);

$item = $order->items()->create([
    'product_name' => 'Widget',
    'quantity' => 2,
    'price' => 50.00
]);

// When item changes, order's composite hash updates automatically
$item->update(['quantity' => 3]);

// Direct database changes are detected by the scheduled job
DB::table('order_items')->where('id', $item->id)->update(['price' => 60.00]);
```

## API Endpoints

The package provides a comprehensive REST API for managing hash detection and publishing:

### Setup

Include the API routes in your application:

```php
// routes/api.php
Route::prefix('api/hash-change-detector')
    ->middleware(['api', 'auth:api']) // Add your preferred middleware
    ->group(base_path('vendor/ameax/laravel-hash-change-detector/routes/api.php'));
```

### Available Endpoints

#### Model Hash Information
```bash
GET /api/hash-change-detector/models/{type}/{id}/hash

# Example
GET /api/hash-change-detector/models/Product/123/hash

# Response
{
    "model_type": "App\\Models\\Product",
    "model_id": 123,
    "attribute_hash": "a1b2c3d4...",
    "composite_hash": "e5f6g7h8...",
    "has_dependents": true,
    "dependent_models": [
        {
            "type": "App\\Models\\OrderItem",
            "id": 456,
            "relation": "order"
        }
    ],
    "updated_at": "2024-01-15T10:30:00Z"
}
```

#### Force Publish (Without Hash Change)
```bash
POST /api/hash-change-detector/models/{type}/{id}/publish

# Publish to specific publishers by ID
{
    "publisher_ids": [1, 2, 3]
}

# Or by name
{
    "publisher_names": ["log", "webhook", "external-api"]
}

# Response
{
    "message": "Publish jobs dispatched",
    "model": {
        "type": "App\\Models\\Product",
        "id": 123
    },
    "publishers": [
        {
            "publisher_id": 1,
            "publisher_name": "log",
            "publish_id": 456
        }
    ]
}
```

#### Publish History
```bash
GET /api/hash-change-detector/models/{type}/{id}/publishes

# With filters
GET /api/hash-change-detector/models/{type}/{id}/publishes?status=failed&publisher_id=1

# Response (paginated)
{
    "data": [
        {
            "id": 789,
            "hash_id": 456,
            "publisher_id": 1,
            "status": "published",
            "attempts": 1,
            "published_at": "2024-01-15T10:30:00Z",
            "publisher": {
                "id": 1,
                "name": "log"
            }
        }
    ],
    "links": {...},
    "meta": {...}
}
```

#### Publisher Management
```bash
# List all publishers
GET /api/hash-change-detector/publishers
GET /api/hash-change-detector/publishers?model_type=Product&status=active

# Update publisher status
PATCH /api/hash-change-detector/publishers/{id}
{
    "status": "inactive"  // or "active"
}
```

#### Operations
```bash
# Trigger change detection
POST /api/hash-change-detector/detect-changes
{
    "model_type": "App\\Models\\Product"  // Optional, omit for all models
}

# Retry failed publishes
POST /api/hash-change-detector/retry-publishes
{
    "publisher_id": 1  // Optional, omit for all publishers
}

# Initialize hashes for existing models
POST /api/hash-change-detector/initialize-hashes
{
    "model_type": "App\\Models\\Product",
    "chunk_size": 100  // Optional, default 100
}
```

#### Statistics
```bash
GET /api/hash-change-detector/stats
GET /api/hash-change-detector/stats?model_type=Product

# Response
{
    "total_hashes": 1000,
    "models_with_dependents": 800,
    "models_without_dependents": 200,
    "total_publishers": 5,
    "active_publishers": 3,
    "publishes_by_status": {
        "published": 450,
        "failed": 25,
        "pending": 10,
        "deferred": 5
    },
    "model_specific": {  // Only if model_type specified
        "model_type": "App\\Models\\Product",
        "total_models": 150,
        "publishers": 2
    }
}
```

### API Usage Examples

```php
// Force publish a model to all active publishers
$response = Http::post('/api/hash-change-detector/models/Product/123/publish');

// Force publish to specific publishers only
$response = Http::post('/api/hash-change-detector/models/Product/123/publish', [
    'publisher_names' => ['webhook', 'external-api']
]);

// Check if model needs publishing
$hash = Http::get('/api/hash-change-detector/models/Product/123/hash');
$history = Http::get('/api/hash-change-detector/models/Product/123/publishes?status=published&limit=1');

if ($hash->json('composite_hash') !== $history->json('data.0.published_hash')) {
    // Model has unpublished changes
}

// Bulk retry failed publishes
$response = Http::post('/api/hash-change-detector/retry-publishes');

// Monitor system health
$stats = Http::get('/api/hash-change-detector/stats');
if ($stats->json('publishes_by_status.failed') > 100) {
    // Alert: Too many failed publishes
}
```

## Testing

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email the maintainers directly instead of using the issue tracker.

## Credits

- [Michael Schmidt](https://github.com/ameax)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.