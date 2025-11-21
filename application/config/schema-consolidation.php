<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Source Schemas
    |--------------------------------------------------------------------------
    |
    | These are the schemas that will be consolidated into the public schema.
    | Set these via environment variables for different environments.
    |
    */
    'source_schemas' => [
        'application' => env('PG_APP_SCHEMA', 'application'),
        'entity_cache' => env('PG_SYNC_SCHEMA', 'entity_cache'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Target Schema
    |--------------------------------------------------------------------------
    |
    | The schema where all tables will be consolidated to.
    | This is typically 'public' for PostgreSQL.
    |
    */
    'target_schema' => env('SCHEMA_CONSOLIDATION_TARGET', 'public'),
];

