<?php

return [

    'app' => [
        'properties' => [
            'username' => env('PG_APP_USERNAME', ''),
            'password' => env('PG_APP_PASSWORD', env('DB_PASSWORD', '')),
            'schema' => env('PG_APP_SCHEMA', 'public'),
        ],
    ],
    'sync' => [
        'properties' => [
            'username' => env('PG_SYNC_USERNAME', ''),
            'password' => env('PG_SYNC_PASSWORD', env('DB_PASSWORD', '')),
            'schema' => env('PG_SYNC_SCHEMA', 'entity_cache'),
        ],
        'name' => env('PG_SYNC_CONNECTION_NAME', 'entity_sync'),
    ],
    'admin' => [
        'name' => env('PG_ADMIN_CONNECTION_NAME', 'pgsql_main'),
    ],
];
