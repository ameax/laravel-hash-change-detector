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

### Change Detection

The package tracks changes in your Eloquent models by generating MD5 hashes of specified attributes. It supports two types of hashes:

1. **Attribute Hash**: MD5 hash of the model's trackable attributes
2. **Composite Hash**: MD5 hash combining the main model's hash with all related models' hashes

When any tracked attribute changes, or when related models are added/modified/deleted, a new composite hash is generated, triggering the publishing process.

### Hash Calculation Methods

1. **PHP-based (via Model Events)**: Automatically calculates hashes when models are created, updated, or deleted through Laravel
2. **MySQL-based (via Direct Queries)**: Efficiently detects changes in bulk for models updated outside of Laravel using SQL functions

### Publishing System

When changes are detected, the package:
1. Creates publish records with status `pending`
2. Dispatches them to your configured publisher classes
3. Implements smart retry logic:
   - First retry: after 30 seconds
   - Second retry: after 5 minutes  
   - Third retry: after 6 hours
   - Final status: `failed`

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

### Detecting Changes via MySQL

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

```bash
composer test
```

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
