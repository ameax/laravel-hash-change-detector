# Laravel Hash Change Detector

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ameax/laravel-hash-change-detector.svg?style=flat-square)](https://packagist.org/packages/ameax/laravel-hash-change-detector)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/ameax/laravel-hash-change-detector/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/ameax/laravel-hash-change-detector/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/ameax/laravel-hash-change-detector/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/ameax/laravel-hash-change-detector/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/ameax/laravel-hash-change-detector.svg?style=flat-square)](https://packagist.org/packages/ameax/laravel-hash-change-detector)

A Laravel package for detecting changes in Eloquent models through hash-based tracking and automatically publishing changes to external systems. Perfect for maintaining data synchronization across multiple platforms, APIs, or services.

## Key Features

- **Hash-based change detection** for models and their relationships
- **Automatic publishing** to external systems when changes are detected  
- **Dual hash calculation** methods (PHP and MySQL) for different use cases
- **Smart retry mechanism** with exponential backoff for failed publishes
- **Support for multiple publishers** per model

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/laravel-hash-change-detector.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/laravel-hash-change-detector)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## How It Works

### System Architecture

The package consists of three main components working together:

1. **Hash Tracking System**: Monitors changes in models and their relationships
2. **Change Detection Engine**: Identifies modifications through PHP events or direct database queries
3. **Publishing Pipeline**: Distributes changes to external systems with retry logic

### Change Detection Lifecycle

#### 1. Model Events (Real-time Detection)

When a model is created, updated, or deleted through Laravel:

```
Model Change → Boot Trait Hook → Calculate Hash → Compare with Stored Hash
     ↓                                                      ↓
Update Parent Hashes ← Fire HashChanged Event ← Hash Different
     ↓                                    ↓
Update Composite Hash          Create/Update Publish Records
                                         ↓
                              Dispatch PublishModelJob → External System
```

#### 2. Direct Database Detection (Bulk Detection)

For models updated outside Laravel (e.g., direct database updates, external scripts):

```
Scheduled Command → DetectChangesJob → SQL Query for All Records
                                              ↓
                                    Calculate Hash in Database
                                              ↓
                                    Compare with Stored Hashes
                                              ↓
                                    Update Changed Records → Trigger Publishing
```

### Hash Calculation Details

#### Attribute Hash Format
Attributes are sorted alphabetically and concatenated with pipe separators:
```
MD5(active|description|name|price)
```

Example:
- Model: `['name' => 'Product', 'price' => 99.99, 'active' => true, 'description' => null]`
- Sorted: `['active' => true, 'description' => null, 'name' => 'Product', 'price' => 99.99]`
- String: `"1||Product|99.99"` (booleans become '1'/'0', nulls become '')
- Hash: `MD5("1||Product|99.99")`

#### Composite Hash Format
All related model hashes are collected, sorted, and concatenated:
```
MD5(main_hash|related_hash_1|related_hash_2|...)
```

### Publishing Workflow

1. **Change Detection**: Hash mismatch triggers `HashChanged` event
2. **Event Handling**: `HandleHashChanged` listener creates publish records
3. **Job Dispatch**: `PublishModelJob` sent to queue
4. **Retry Logic**: Failed attempts follow exponential backoff
5. **Status Tracking**: Each attempt updates the `publishes` table

### Database Schema

The package uses three main tables:

- **`hashes`**: Stores attribute and composite hashes for all tracked models
- **`publishers`**: Defines available publishers for each model type
- **`publishes`**: Tracks publishing attempts and their status

## Installation

You can install the package via composer:

```bash
composer require ameax/laravel-hash-change-detector
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-hash-change-detector-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-hash-change-detector-config"
```

## Usage

### Relation Tracking

The package automatically tracks changes in related models using an event-driven approach:

1. **Event-Driven Updates**: When a related model changes, it fires a `RelatedModelUpdated` event
2. **Parent Notification**: Child models define their parent relationships via `getParentModels()`
3. **Automatic Reloading**: Parent models automatically reload their tracked relations before recalculating hashes
4. **Clean Separation**: Each model is responsible for its own hash calculation

#### Implementation Example

```php
// Child Model
class OrderItem extends Model implements Hashable
{
    use InteractsWithHashes;
    
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    
    // Define which models should be notified when this model changes
    public function getParentModels(): Collection
    {
        return collect([$this->order]);
    }
}

// Parent Model
class Order extends Model implements Hashable
{
    use InteractsWithHashes;
    
    public function getHashableRelations(): array
    {
        return ['orderItems']; // Tracks changes in items
    }
}
```

#### Update Flow
```
OrderItem price changes
  ↓
OrderItem hash updates → Fires RelatedModelUpdated event
  ↓
Event listener calls getParentModels() on OrderItem
  ↓
Order model is notified → Reloads 'orderItems' relation
  ↓
Order recalculates composite hash with fresh data
  ↓
Publishing triggered if hash changed
```

#### Benefits

- **Loose Coupling**: Child models don't need to know how parent models track relations
- **Multiple Parents**: A model can easily notify multiple parent models
- **Maintainable**: Parent models can change their tracked relations without updating children
- **Testable**: Event-driven architecture is easier to test in isolation

### Making a Model Hashable

Implement the `Hashable` interface on your model:

```php
use ameax\HashChangeDetector\Contracts\Hashable;
use ameax\HashChangeDetector\Traits\InteractsWithHashes;

class Product extends Model implements Hashable
{
    use InteractsWithHashes;
    
    public function getHashableAttributes(): array
    {
        return ['name', 'price', 'description', 'sku'];
    }
    
    public function getHashableRelations(): array
    {
        return [
            'variants',           // hasMany
            'categories',         // belongsToMany
            'details.specifications'  // hasManyThrough
        ];
    }
}
```

### Creating Publishers

Create a publisher class that implements the `Publisher` interface:

```php
use ameax\HashChangeDetector\Contracts\Publisher;

class ProductApiPublisher implements Publisher
{
    public function publish(Model $model, array $data): bool
    {
        // Send to external API
        $response = Http::post('https://api.example.com/products', [
            'id' => $model->id,
            'data' => $data,
            'hash' => $model->getCurrentHash(),
        ]);
        
        return $response->successful();
    }
    
    public function getData(Model $model): array
    {
        // Prepare data for publishing
        return [
            'product' => $model->toArray(),
            'variants' => $model->variants->toArray(),
            'categories' => $model->categories->pluck('name'),
        ];
    }
}
```

### Configuring Publishers

Register publishers for your models:

```php
use ameax\HashChangeDetector\Facades\HashChangeDetector;

// In a service provider or bootstrap file
HashChangeDetector::registerPublisher(
    'product-api',
    Product::class,
    ProductApiPublisher::class
);

// Or use the command
php artisan laravel-hash-change-detector:publisher:create "Product API" Product ProductApiPublisher
```

### Available Commands

The package provides several Artisan commands:

```bash
# Detect changes for all or specific models
php artisan hash-detector:detect-changes [model-class]

# Retry deferred publishes
php artisan hash-detector:retry-publishes

# Manage publishers
php artisan hash-detector:publisher:create "Name" "Model\Class" "Publisher\Class"
php artisan hash-detector:publisher:list [--model=Model\Class] [--status=active]
php artisan hash-detector:publisher:toggle {id} [--activate] [--deactivate]
```

### Scheduling

Add these to your `app/Console/Kernel.php` for automatic processing:

```php
protected function schedule(Schedule $schedule)
{
    // Detect changes in models updated outside Laravel
    $schedule->command('hash-detector:detect-changes')
        ->everyFiveMinutes();
    
    // Retry deferred publishes
    $schedule->command('hash-detector:retry-publishes')
        ->everyFiveMinutes();
}
```

### Detecting Changes via Direct Database

For models updated outside Laravel, use the bulk change detector:

```php
// In a scheduled command
use ameax\HashChangeDetector\Jobs\DetectChangesJob;

// Check specific model type
DetectChangesJob::dispatch(Product::class);

// Or check all registered hashable models
DetectChangesJob::dispatch();
```

## Testing

The package includes comprehensive test coverage using Pest PHP. Run tests with:

```bash
composer test
```

### Test Models

The test suite includes example models that demonstrate best practices:

#### TestModel (Parent)
```php
class TestModel extends Model implements Hashable
{
    use InteractsWithHashes;
    
    public function getHashableAttributes(): array
    {
        return ['name', 'description', 'price', 'active'];
    }
    
    public function getHashableRelations(): array
    {
        return ['testRelations'];
    }
}
```

#### TestRelationModel (Child)
```php
class TestRelationModel extends Model implements Hashable
{
    use InteractsWithHashes;
    
    public function getParentModels(): Collection
    {
        $parents = collect();
        
        if ($this->testModel) {
            $parents->push($this->testModel);
        }
        
        return $parents;
    }
}
```

### Test Coverage

The test suite covers:

1. **Change Detection Tests** (`tests/ChangeDetectionTest.php`)
   - Hash creation on model creation
   - Hash updates on attribute changes
   - Null value handling
   - Boolean value conversions
   - Parent composite hash updates
   - Related model tracking
   - Consistent hash ordering

2. **MySQL Detection Tests** (`tests/MySQLChangeDetectionTest.php`)
   - Direct database change detection
   - Bulk change processing
   - SQLite compatibility for testing
   - Hash calculation consistency between PHP and SQL

### Key Testing Insights

1. **Attribute Ordering**: Attributes are always sorted alphabetically for consistent hashing
2. **Type Handling**: 
   - Booleans: `true` → `'1'`, `false` → `'0'`
   - Nulls: `null` → `''` (empty string)
   - Decimals: Preserved with precision (e.g., `99.99`)
3. **Parent Updates**: Child models must explicitly call `updateParentHashes()` to propagate changes

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Michael Schmidt](https://github.com/ameax)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
