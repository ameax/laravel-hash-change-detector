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
php artisan vendor:publish --tag="laravel-hash-change-detector-migrations"
php artisan migrate
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag="laravel-hash-change-detector-config"
```

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
    
    public function getHashableRelations(): array
    {
        return []; // No related models to track
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
     * Define which relationships to track
     */
    public function getHashableRelations(): array
    {
        return [
            'orderItems',     // HasMany relationship
            'shipping',       // HasOne relationship
        ];
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
    
    public function getHashableRelations(): array
    {
        return []; // Child models typically don't track relations
    }
    
    /**
     * Define which parent models should be notified of changes
     */
    public function getParentModels(): Collection
    {
        return collect([$this->order]);
    }
    
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
```

### How It Works

1. **Individual Hashes**: Each model has its own hash based on `getHashableAttributes()`
2. **Composite Hashes**: Parent models also have a composite hash that includes hashes from all tracked relations
3. **Automatic Updates**: When a child changes, it notifies its parents to recalculate their composite hashes
4. **Event Driven**: All updates trigger events that you can listen to

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

### Creating a Publisher

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
public function getHashableRelations(): array
{
    return [
        'orderItems',           // Direct relation
        'orderItems.product',   // Nested relation
    ];
}
```

### Multiple Parents

A model can notify multiple parent models:

```php
public function getParentModels(): Collection
{
    return collect([
        $this->order,
        $this->warehouse,
        $this->invoice
    ])->filter(); // Filter removes nulls
}
```

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
    
    public function getHashableRelations(): array
    {
        return ['items'];
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
    
    public function getHashableRelations(): array
    {
        return [];
    }
    
    public function getParentModels(): Collection
    {
        return collect([$this->order]);
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

## Testing

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email security@example.com instead of using the issue tracker.

## Credits

- [Michael Schmidt](https://github.com/ameax)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.