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

    /*
    |--------------------------------------------------------------------------
    | Entity Cache Table Names
    |--------------------------------------------------------------------------
    |
    | The specific table names in the entity_cache schema that need to be moved.
    | These are hardcoded since there are only 3 tables and they use a different
    | connection (sync_user) during creation.
    |
    */
    'entity_cache_tables' => [
        'cached_classifier_values',
        'cached_institutions',
        'cached_institution_users',
    ],
];
