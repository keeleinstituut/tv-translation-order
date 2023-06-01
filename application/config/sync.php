<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Base URL of other services
    |--------------------------------------------------------------------------
    */
    'classifier_service_base_url' => env('CLASSIFIER_SERVICE_BASE_URL', 'http://127.0.0.1:8000/api'),
    'authorization_service_base_url' => env('AUTHORIZATION_SERVICE_BASE_URL', 'http://127.0.0.1:8003/api'),
];
