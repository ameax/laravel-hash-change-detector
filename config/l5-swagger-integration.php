<?php

declare(strict_types=1);

/**
 * L5-Swagger Integration Configuration for Laravel Hash Change Detector
 * 
 * Add this configuration to your main application's config/l5-swagger.php file
 * to include the Hash Change Detector API documentation.
 */

return [
    /*
     * Add these paths to your l5-swagger configuration's 'annotations' array
     * to include Hash Change Detector API documentation
     */
    'annotations' => [
        // Your existing application paths...
        base_path('app'),
        
        // Add Hash Change Detector API controllers
        base_path('vendor/ameax/laravel-hash-change-detector/src/Http/Controllers'),
    ],

    /*
     * Example complete l5-swagger configuration with Hash Change Detector included:
     */
    'example_config' => [
        'default' => [
            'api' => [
                'title' => 'Your Application API with Hash Change Detector',
            ],
            'routes' => [
                'api' => 'api/documentation',
            ],
            'paths' => [
                'annotations' => [
                    base_path('app'),
                    base_path('vendor/ameax/laravel-hash-change-detector/src/Http/Controllers'),
                ],
                'views' => base_path('resources/views/vendor/l5-swagger'),
                'base' => env('L5_SWAGGER_BASE_PATH', null),
                'swagger_ui_assets_path' => env('L5_SWAGGER_UI_ASSETS_PATH', 'vendor/swagger-api/swagger-ui/dist/'),
                'excludes' => [],
            ],
            'securityDefinitions' => [
                'bearer' => [
                    'type' => 'http',
                    'description' => 'Authorization token obtained from login',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT',
                ],
            ],
            'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', false),
            'generate_yaml_copy' => env('L5_SWAGGER_GENERATE_YAML_COPY', false),
            'proxy' => false,
            'operations_sort' => env('L5_SWAGGER_OPERATIONS_SORT', null),
            'validator_url' => null,
            'ui' => [
                'display' => [
                    'doc_expansion' => env('L5_SWAGGER_UI_DOC_EXPANSION', 'none'),
                    'filter' => env('L5_SWAGGER_UI_FILTERS', true),
                ],
            ],
        ],
    ],
];