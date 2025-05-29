<?php

// config for ameax/HashChangeDetector
return [
    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Here you can configure the table names used by the package. This allows
    | you to avoid naming conflicts with existing tables in your application.
    |
    */
    'tables' => [
        'hashes' => 'hashes',
        'publishers' => 'publishers',
        'publishes' => 'publishes',
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the retry intervals for failed publish attempts.
    | Times are in seconds.
    |
    */
    'retry_intervals' => [
        1 => 30,        // First retry after 30 seconds
        2 => 300,       // Second retry after 5 minutes (300 seconds)
        3 => 21600,     // Third retry after 6 hours (21600 seconds)
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which queues should be used for processing.
    |
    */
    'queues' => [
        'publish' => 'default',
        'detect_changes' => 'default',
    ],

    /*
    |--------------------------------------------------------------------------
    | Hash Algorithm
    |--------------------------------------------------------------------------
    |
    | The hash algorithm to use for generating hashes.
    | Supported: "md5", "sha256"
    |
    */
    'hash_algorithm' => 'md5',
];
