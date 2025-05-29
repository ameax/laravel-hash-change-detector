# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

**Testing:**
- `composer test` - Run Pest tests
- `composer test-coverage` - Run tests with coverage report
- `vendor/bin/pest --filter "test name"` - Run a single test

**Code Quality:**
- `composer analyse` - Run PHPStan static analysis
- `composer format` - Run Laravel Pint code formatter

**Package Management:**
- `composer install` - Install dependencies
- `composer prepare` - Run after autoload dump (discovers testbench packages)

## Architecture

This is a Laravel package laravel-hash-change-detector using Spatie's package tools. Key components:

**Service Provider:** Registers package services, commands, config, migrations, and views with Laravel.

**Facades:** Provides static interface to package functionality.

**Commands:** Artisan commands are registered in the service provider.

**Testing:** Uses Pest PHP with Orchestra Testbench for Laravel package testing. Architecture tests ensure code quality standards.

**Configuration:** Package config files are publishable. The laravel-hash-change-detector uses placeholders (`ameax`, `laravel-hash-change-detector`, etc.) that should be replaced via `configure.php` script.

**Database:** Supports migrations with publishable stubs. Factory support for testing models.

## Initial Setup

Before starting development on a new package:
1. Run `php configure.php` to replace all placeholders
2. The script will prompt for package details and configure tooling preferences
3. After configuration, the laravel-hash-change-detector structure will be customized for your specific package