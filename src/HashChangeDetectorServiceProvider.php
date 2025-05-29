<?php

namespace ameax\HashChangeDetector;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use ameax\HashChangeDetector\Commands\HashChangeDetectorCommand;
use ameax\HashChangeDetector\Commands\DetectChangesCommand;
use ameax\HashChangeDetector\Commands\RetryPublishesCommand;
use ameax\HashChangeDetector\Commands\CreatePublisherCommand;
use ameax\HashChangeDetector\Commands\ListPublishersCommand;
use ameax\HashChangeDetector\Commands\TogglePublisherCommand;
use ameax\HashChangeDetector\Events\HashChanged;
use ameax\HashChangeDetector\Listeners\HandleHashChanged;
use Illuminate\Support\Facades\Event;

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
            ->hasMigration('create_hash_change_detector_tables')
            ->hasCommands([
                HashChangeDetectorCommand::class,
                DetectChangesCommand::class,
                RetryPublishesCommand::class,
                CreatePublisherCommand::class,
                ListPublishersCommand::class,
                TogglePublisherCommand::class,
            ]);
    }

    public function boot(): void
    {
        parent::boot();

        // Register event listeners
        Event::listen(HashChanged::class, HandleHashChanged::class);
    }
}
