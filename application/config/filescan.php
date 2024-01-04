<?php

return [
    'base_url' => env('FILESCAN_API_URL', 'http://localhost:8010/api'),
    'timeout' => env('FILESCAN_API_TIMEOUT', 30),
    'connection_timeout' => env('FILESCAN_API_CONNECTION_TIMEOUT', 30),
];
