<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Configure CORS settings for API requests from desktop client.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        // Desktop client origins - update these for production
        'http://localhost',
        'http://localhost:*',
        'http://127.0.0.1',
        'http://127.0.0.1:*',
        // Add your desktop client URLs here
        // 'https://your-desktop-client.app',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'Accept',
        'Origin',
    ],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => true,

];
