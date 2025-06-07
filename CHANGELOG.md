# Changelog

All notable changes to `laravel-hash-change-detector` will be documented in this file.

## [Unreleased]

## v1.1.0 - 2025-06-07

### Added

- Automatic parent model hash regeneration when related models change
  - New `getParentModelRelations()` method in Hashable interface
  - Support for nested relations (e.g., 'user.country') to notify indirect parents
  - Proper handling of model deletion events

### Changed

- Simplified parent model notification system - models now explicitly declare parent relations
- Improved HandleRelatedModelUpdated listener to use the new parent relations method

### Removed

- Deprecated `getParentModels()` method in favor of `getParentModelRelations()`

## v1.0.5 - 2025-05-30

**Full Changelog**: https://github.com/ameax/laravel-hash-change-detector/compare/v1.0.4...v1.0.5

## v1.0.1 - 2025-05-30

**Full Changelog**: https://github.com/ameax/laravel-hash-change-detector/commits/v1.0.1

## v1.0.0 - 2025-05-29

**Full Changelog**: https://github.com/ameax/laravel-hash-change-detector/commits/v1.0.0

### Added

- Initial release of Laravel Hash Change Detector
  
- Hash-based change detection for Eloquent models
  
- Support for tracking related models with composite hashes
  
- Direct database change detection for changes made outside Laravel
  
- Publishing system with retry logic and multiple publisher support
  
- Built-in publishers: LogPublisher and HttpPublisher (abstract)
  
- Support for read-only models (database views, external tables)
  
- External synchronization capabilities
  
- HasManyThrough relationship support
  
- Comprehensive API with OpenAPI/Swagger documentation
  
- Full CRUD API for publisher management
  
- Force publish functionality without hash changes
  
- Artisan commands for management:
  
  - `hash-detector:detect-changes` - Manually trigger change detection
  - `hash-detector:retry-publishes` - Retry failed publishes
  - `hash-detector:create-publisher` - Create a new publisher
  - `hash-detector:list-publishers` - List all publishers
  - `hash-detector:toggle-publisher` - Enable/disable publishers
  - `hash-detector:initialize-hashes` - Initialize hashes for existing models
  
- Comprehensive test suite with 146 tests
  
- Support for Laravel 10, 11, and 12
  
- PHP 8.2+ support
  

### Security

- Environment-based configuration
- No hardcoded credentials or secrets
- Secure hash generation using configurable algorithms

## [1.0.0] - TBD

- First stable release
