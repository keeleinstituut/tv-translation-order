<?php

return [
    'base_url' => env('MATECAT_API_URL', 'http://localhost:8080/api'),
    'timeout' => env('MATECAT_API_TIMEOUT', 30),
    'connection_timeout' => env('MATECAT_API_CONNECTION_TIMEOUT', 30),
];
