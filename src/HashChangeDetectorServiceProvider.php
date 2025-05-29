<?php

namespace ameax\HashChangeDetector;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use ameax\HashChangeDetector\Commands\HashChangeDetectorCommand;

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
            ->hasCommand(HashChangeDetectorCommand::class);
    }
}
