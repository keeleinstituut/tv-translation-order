<?php

return [
    'base_url' => env('CATV2_API_URL', 'http://localhost:6001/api'),
    'timeout' => env('CATV2_API_TIMEOUT', 30),
    'connection_timeout' => env('CATV2_API_CONNECTION_TIMEOUT', 30),
];
