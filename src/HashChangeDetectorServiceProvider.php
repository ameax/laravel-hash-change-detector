<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector;

use ameax\HashChangeDetector\Commands\CreatePublisherCommand;
use ameax\HashChangeDetector\Commands\DetectChangesCommand;
use ameax\HashChangeDetector\Commands\HashChangeDetectorCommand;
use ameax\HashChangeDetector\Commands\InitializeHashesCommand;
use ameax\HashChangeDetector\Commands\ListPublishersCommand;
use ameax\HashChangeDetector\Commands\RetryPublishesCommand;
use ameax\HashChangeDetector\Commands\TogglePublisherCommand;
use ameax\HashChangeDetector\Events\HashChanged;
use ameax\HashChangeDetector\Events\RelatedModelUpdated;
use ameax\HashChangeDetector\Listeners\HandleHashChanged;
use ameax\HashChangeDetector\Listeners\HandleRelatedModelUpdated;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class HashChangeDetectorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-hash-change-detector')
            ->hasConfigFile()
            ->hasViews()
            ->publishesMigrations()
            ->hasCommands([
                HashChangeDetectorCommand::class,
                DetectChangesCommand::class,
                RetryPublishesCommand::class,
                CreatePublisherCommand::class,
                ListPublishersCommand::class,
                TogglePublisherCommand::class,
                InitializeHashesCommand::class,
            ])
            ->hasRoute('api');
    }

    public function boot(): void
    {
        parent::boot();

        // Register event listeners
        Event::listen(HashChanged::class, HandleHashChanged::class);
        Event::listen(RelatedModelUpdated::class, HandleRelatedModelUpdated::class);
    }
}
