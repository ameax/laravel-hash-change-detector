# Changelog

All notable changes to `laravel-hash-change-detector` will be documented in this file.

## [Unreleased]

## v1.2.1 - 2025-06-07

### Fixed

- Fixed HasMany relationship notifications not updating dependent models
  - Added proper support for collection relationships (HasMany, BelongsToMany)
  - Force fresh load of relations to ensure accurate dependent tracking
  - Update dependent references even when hash hasn't changed
  - Added `updateHash()` method to Hashable interface

## v1.2.0 - 2025-06-07

### Added

- Multiple parent-child relationship tracking via pivot table
  - New `hash_dependents` table for tracking multiple dependent relationships
  - Support for models with multiple parents (e.g., Comment belongs to both Post and User)
  - Automatic parent hash updates when direct database deletions are detected
  - New `HashDependent` model for managing the pivot relationships

### Changed

- **BREAKING**: Renamed methods for clarity:
  - `getHashableRelations()` → `getHashCompositeDependencies()`
  - `getParentModelRelations()` → `getHashRelationsToNotifyOnChange()`
- **BREAKING**: Database schema changes:
  - Renamed `hash_parents` table to `hash_dependents`
  - Removed `main_model_type` and `main_model_id` columns from `hashes` table
  - Changed foreign key from `child_hash_id` to `hash_id` in dependents table
- **BREAKING**: Model and API changes:
  - Renamed `HashParent` model to `HashDependent`
  - Changed `hasParents()` to `hasDependents()` in Hash model
  - Updated API responses: `has_parents` → `has_dependents`, `parent_models` → `dependent_models`
- Improved naming conventions throughout to better reflect dependency relationships

### Fixed

- PHPStan errors resolved with proper type hints and OpenAPI dependency
- Removed env() calls from config files for better Laravel best practices

### Dependencies

- Added `zircote/swagger-php` as dev dependency for OpenAPI attributes support

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
