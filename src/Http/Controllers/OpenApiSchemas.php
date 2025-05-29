<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Http\Controllers;

use OpenApi\Attributes as OA;

/**
 * Shared OpenAPI schema definitions for the Hash Change Detector API.
 * 
 * This file contains reusable schema components that are referenced
 * throughout the API documentation.
 */

#[OA\Schema(
    schema: 'Hash',
    description: 'Hash record for tracking model changes',
    properties: [
        new OA\Property(property: 'id', type: 'integer', description: 'Hash ID'),
        new OA\Property(property: 'hashable_type', type: 'string', description: 'Model class name'),
        new OA\Property(property: 'hashable_id', type: 'integer', description: 'Model ID'),
        new OA\Property(property: 'attribute_hash', type: 'string', description: 'Hash of model attributes'),
        new OA\Property(property: 'composite_hash', type: 'string', nullable: true, description: 'Combined hash including relations'),
        new OA\Property(property: 'main_model_type', type: 'string', nullable: true, description: 'Parent model type if this is a related model'),
        new OA\Property(property: 'main_model_id', type: 'integer', nullable: true, description: 'Parent model ID if this is a related model'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time')
    ]
)]
#[OA\Schema(
    schema: 'PublishStatus',
    type: 'string',
    enum: ['pending', 'dispatched', 'published', 'failed', 'deferred'],
    description: 'Status of a publish operation'
)]
#[OA\Schema(
    schema: 'PublisherStatus',
    type: 'string',
    enum: ['active', 'inactive'],
    description: 'Status of a publisher'
)]
#[OA\Schema(
    schema: 'ValidationError',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\Schema(
                type: 'array',
                items: new OA\Items(type: 'string')
            ),
            example: ['field_name' => ['The field is required.']]
        )
    ]
)]
#[OA\Schema(
    schema: 'ErrorResponse',
    properties: [
        new OA\Property(property: 'error', type: 'string', description: 'Error message')
    ]
)]
#[OA\Schema(
    schema: 'SuccessResponse',
    properties: [
        new OA\Property(property: 'message', type: 'string', description: 'Success message')
    ]
)]
#[OA\Schema(
    schema: 'PublisherType',
    properties: [
        new OA\Property(property: 'class', type: 'string', description: 'Fully qualified class name'),
        new OA\Property(property: 'name', type: 'string', description: 'Human-readable name'),
        new OA\Property(property: 'description', type: 'string', description: 'Description of what this publisher does'),
        new OA\Property(property: 'abstract', type: 'boolean', nullable: true, description: 'Whether this is an abstract base class')
    ]
)]
#[OA\Schema(
    schema: 'PublishRequest',
    properties: [
        new OA\Property(
            property: 'publisher_ids',
            type: 'array',
            items: new OA\Items(type: 'integer'),
            description: 'Array of publisher IDs to publish to'
        ),
        new OA\Property(
            property: 'publisher_names',
            type: 'array',
            items: new OA\Items(type: 'string'),
            description: 'Array of publisher names to publish to'
        )
    ]
)]
#[OA\Schema(
    schema: 'PaginationLinks',
    properties: [
        new OA\Property(property: 'first', type: 'string', nullable: true, format: 'uri'),
        new OA\Property(property: 'last', type: 'string', nullable: true, format: 'uri'),
        new OA\Property(property: 'prev', type: 'string', nullable: true, format: 'uri'),
        new OA\Property(property: 'next', type: 'string', nullable: true, format: 'uri')
    ]
)]
#[OA\Schema(
    schema: 'PublisherStoreRequest',
    required: ['name', 'model_type', 'publisher_class'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255),
        new OA\Property(property: 'model_type', type: 'string'),
        new OA\Property(property: 'publisher_class', type: 'string'),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive'], default: 'active'),
        new OA\Property(property: 'config', type: 'object', nullable: true)
    ]
)]
#[OA\Schema(
    schema: 'PublisherUpdateRequest',
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive']),
        new OA\Property(property: 'config', type: 'object', nullable: true)
    ]
)]
#[OA\Schema(
    schema: 'BulkUpdateRequest',
    required: ['publisher_ids', 'status'],
    properties: [
        new OA\Property(
            property: 'publisher_ids',
            type: 'array',
            items: new OA\Items(type: 'integer')
        ),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive'])
    ]
)]
#[OA\Schema(
    schema: 'InitializeHashesRequest',
    required: ['model_type'],
    properties: [
        new OA\Property(property: 'model_type', type: 'string'),
        new OA\Property(property: 'chunk_size', type: 'integer', minimum: 1, maximum: 1000, default: 100)
    ]
)]
#[OA\Schema(
    schema: 'DetectChangesRequest',
    properties: [
        new OA\Property(property: 'model_type', type: 'string', nullable: true)
    ]
)]
#[OA\Schema(
    schema: 'RetryPublishesRequest',
    properties: [
        new OA\Property(property: 'publisher_id', type: 'integer', nullable: true)
    ]
)]
#[OA\Schema(
    schema: 'PublisherTestRequest',
    required: ['model_id'],
    oneOf: [
        new OA\Schema(
            required: ['publisher_id', 'model_id'],
            properties: [
                new OA\Property(property: 'publisher_id', type: 'integer'),
                new OA\Property(property: 'model_id', type: 'integer'),
                new OA\Property(property: 'config', type: 'object', nullable: true)
            ]
        ),
        new OA\Schema(
            required: ['publisher_class', 'model_type', 'model_id'],
            properties: [
                new OA\Property(property: 'publisher_class', type: 'string'),
                new OA\Property(property: 'model_type', type: 'string'),
                new OA\Property(property: 'model_id', type: 'integer'),
                new OA\Property(property: 'config', type: 'object', nullable: true)
            ]
        )
    ]
)]
class OpenApiSchemas
{
    // This class serves as a container for OpenAPI schema definitions
    // No implementation needed
}