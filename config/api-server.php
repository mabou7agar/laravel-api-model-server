<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Route Prefix
    |--------------------------------------------------------------------------
    |
    | This value is the prefix used for all API routes registered by this package.
    | For example, if set to 'api', routes will be prefixed with '/api'.
    |
    */
    'route_prefix' => 'api',

    /*
    |--------------------------------------------------------------------------
    | Exposed Models
    |--------------------------------------------------------------------------
    |
    | This array contains the fully qualified class names of models that should
    | be exposed via the API. Each model must implement the ApiExposable interface.
    |
    */
    'exposed_models' => [
        // Example: App\Models\User::class,
        App\Models\User::class, // Assuming User is the model you want to expose
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for OAuth authentication using Laravel Passport.
    |
    */
    'use_oauth' => env('API_SERVER_USE_OAUTH', true),
    'token_lifetime' => env('API_SERVER_TOKEN_LIFETIME', 60 * 24), // 1 day in minutes
    'refresh_token_lifetime' => env('API_SERVER_REFRESH_TOKEN_LIFETIME', 60 * 24 * 30), // 30 days in minutes

    /*
    |--------------------------------------------------------------------------
    | Encrypted Query Parameters
    |--------------------------------------------------------------------------
    |
    | Configuration for the encrypted query parameter feature.
    |
    */
    'use_encrypted_queries' => env('API_SERVER_USE_ENCRYPTED_QUERIES', true),
    'signature_secret' => env('API_SERVER_SIGNATURE_SECRET', env('APP_KEY')),
    'query_expiration' => env('API_SERVER_QUERY_EXPIRATION', 60 * 5), // 5 minutes in seconds

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Configure performance-related settings for the API server.
    |
    */
    
    // Caching configuration
    'use_cache' => env('API_SERVER_USE_CACHE', true),
    'cache_prefix' => env('API_SERVER_CACHE_PREFIX', 'api_server_'),
    'cache_ttl' => env('API_SERVER_CACHE_TTL', 300), // 5 minutes
    
    // Query optimization
    'default_per_page' => env('API_SERVER_DEFAULT_PER_PAGE', 15),
    'max_per_page' => env('API_SERVER_MAX_PER_PAGE', 100),
    'default_sort_limit' => env('API_SERVER_DEFAULT_SORT_LIMIT', 1000),
    
    // Profiling and monitoring
    'enable_profiling' => env('API_SERVER_ENABLE_PROFILING', false),
    'include_profiling_headers' => env('API_SERVER_INCLUDE_PROFILING_HEADERS', false),
    'slow_request_threshold' => env('API_SERVER_SLOW_REQUEST_THRESHOLD', 1.0), // seconds
    'store_metrics' => env('API_SERVER_STORE_METRICS', false),

    // Response compression
    'use_compression' => env('API_SERVER_USE_COMPRESSION', true),
    'compression_level' => env('API_SERVER_COMPRESSION_LEVEL', 6), // 0-9, where 9 is maximum compression
    'compression_min_size' => env('API_SERVER_COMPRESSION_MIN_SIZE', 1024), // Don't compress responses smaller than 1KB

    // ETag caching
    'use_etags' => env('API_SERVER_USE_ETAGS', true),
    'etag_cache_max_age' => env('API_SERVER_ETAG_CACHE_MAX_AGE', 3600), // 1 hour

    // Database connection pooling
    'use_connection_pooling' => env('API_SERVER_USE_CONNECTION_POOLING', true),
    'db_pool_max_connections' => env('API_SERVER_DB_POOL_MAX_CONNECTIONS', 10),
    'db_pool_idle_timeout' => env('API_SERVER_DB_POOL_IDLE_TIMEOUT', 300), // 5 minutes

    // Parallel query processing
    'use_parallel_queries' => env('API_SERVER_USE_PARALLEL_QUERIES', true),
    'max_parallel_queries' => env('API_SERVER_MAX_PARALLEL_QUERIES', 3),

    /*
    |--------------------------------------------------------------------------
    | File Uploads
    |--------------------------------------------------------------------------
    |
    | Configuration for file uploads in API requests.
    |
    */
    'default_upload_disk' => 'public',
    'default_upload_path' => 'uploads',
    'max_file_size' => 10 * 1024, // 10MB in kilobytes
];
