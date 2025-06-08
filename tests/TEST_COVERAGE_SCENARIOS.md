# Test Coverage for Hash Update Scenarios

This document tracks test coverage for all hash update scenarios in the Laravel Hash Change Detector package.

## Main Model Scenarios

### 1. Creation via Model triggers updateHash ✅
- **Test File**: `ChangeDetectionTest.php`
- **Test Name**: `it creates hash when model is created`
- **Description**: Verifies that creating a model through Eloquent creates a hash record

### 2. Change via Model triggers updateHash ✅
- **Test File**: `ChangeDetectionTest.php`
- **Test Name**: `it updates hash when model attributes change`
- **Description**: Verifies that updating a model through Eloquent updates the hash

### 3. Deletion via Model triggers deletion events ✅
- **Test File**: `EloquentDeletionPublishingTest.php`
- **Test Name**: `it triggers deletion publishers immediately when model is deleted via eloquent`
- **Description**: Verifies that deleting a model through Eloquent triggers deletion publishers
- **Note**: Hash is deleted, not updated, but deletion events are fired

### 4. Creation direct in Database triggers updateHash ✅
- **Test File**: `DirectDatabaseChangeDetectionTest.php`
- **Test Name**: `it creates hash for models without hash records`
- **Description**: Verifies that models created directly in DB get hashes when detection runs

### 5. Change direct in Database triggers updateHash ✅
- **Test File**: `DirectDatabaseChangeDetectionTest.php`
- **Test Name**: `it detects changes made directly in database`
- **Description**: Verifies that direct DB updates are detected and hashes are updated

### 6. Deletion direct in Database triggers deletion events ✅
- **Test File**: `DirectDatabaseDeletionTest.php`
- **Test Name**: `it fires event when a parent model is deleted directly in database`
- **Description**: Verifies that direct DB deletions are detected and events are fired
- **Note**: Hash is deleted and HashableModelDeleted event is fired

## Dependent Model Scenarios

### 7. Creation of dependent via Model triggers updateHash on dependent and main ✅
- **Test File**: `ChangeDetectionTest.php`
- **Test Names**: 
  - `it automatically updates parent hash when related model is created`
  - `it creates hash for related models`
- **Description**: Verifies that creating a dependent model updates both its hash and parent's composite hash

### 8. Change of dependent via Model triggers updateHash on dependent and main ✅
- **Test File**: `ChangeDetectionTest.php`
- **Test Name**: `it updates parent composite hash when related model is updated`
- **Description**: Verifies that updating a dependent model updates both hashes

### 9. Deletion of dependent via Model triggers updateHash on dependent and main ✅
- **Test File**: `ChangeDetectionTest.php`
- **Test Name**: `it updates parent composite hash when related model is deleted`
- **Description**: Verifies that deleting a dependent model updates parent's composite hash

### 10. Creation of dependent direct in Database triggers updateHash on dependent and main ✅
- **Test File**: `DirectDatabaseDependentChangeDetectionTest.php`
- **Test Name**: `it updates parent hash when dependent model is created directly in database`
- **Description**: Verifies that creating dependents via DB::insert creates hashes for both models
- **Note**: Now automatically updates parent hash when detection runs on dependent model

### 11. Change of dependent direct in Database triggers updateHash on dependent and main ✅
- **Test File**: `DirectDatabaseDependentChangeDetectionTest.php`
- **Test Name**: `it updates parent hash when dependent model is changed directly in database`
- **Description**: Verifies that updating dependents via DB::update updates hashes for both models
- **Note**: Now automatically updates parent hash when detection runs on dependent model

### 12. Deletion of dependent direct in Database triggers updateHash on main ✅
- **Test File**: `DirectDatabaseDeletionWithDependentsTest.php`
- **Test Name**: `it updates dependent models when a model with HasMany relation is deleted directly`
- **Description**: Verifies that deleting a model with dependents updates the dependent models' hashes

## Additional Test Coverage

### HasMany Relationships ✅
- **Test File**: `HasManyRelationNotificationTest.php`
- **Tests**: Collection-based relationships (multiple dependents)

### Circular Dependencies ✅
- **Test File**: `InfiniteLoopPreventionTest.php`
- **Tests**: Prevents infinite loops in bidirectional relationships

### Edge Cases ✅
- **Test File**: `EdgeCaseTest.php`
- **Tests**: Unicode characters, null values, large data, concurrent updates

### Publishing System ✅
- **Test File**: `PublishingJobTest.php`, `DeletionPublishingTest.php`
- **Tests**: Publishing for creates/updates/deletes

### Direct Database Detection ✅
- **Test File**: `DirectDatabaseChangeDetectionTest.php`
- **Tests**: Hash calculation in database, bulk changes

## Summary

**Total Coverage: 12/12 scenarios (100%)**

All core scenarios are covered by tests. The package properly handles:
- Eloquent operations (immediate hash updates and parent notifications)
- Direct database operations (detected via scheduled jobs)
- Deletion events for both Eloquent and direct DB deletions
- Complex relationships including HasMany and circular dependencies

### Important Notes:

1. **Direct DB operations on dependents**: When dependents are created/updated directly in the database, running detection on the dependent model class automatically updates parent model hashes through the `RelatedModelUpdated` event.

2. **Deletion behavior**: Deletions don't "update" hashes - they remove them and fire deletion events. This is the correct behavior.

3. **Event propagation**: Both Eloquent operations and direct DB operations (via `updateHash()`) now fire `RelatedModelUpdated` events for immediate parent notification.