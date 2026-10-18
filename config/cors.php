<?php

return [
    'paths' => ['v1/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_filter([
        env('ADMIN_URL', 'https://admin.maraghehcity.ir'),
        env('APP_ENV') === 'local' ? 'http://localhost:4200' : null,
    ]),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 86400,
    'supports_credentials' => false,
];
