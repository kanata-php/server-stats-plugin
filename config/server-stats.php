<?php

return [
    'endpoint' => env('SERVER_STATS_ENDPOINT', '/metrics'),
    'secure-metrics-jwt' => env('SERVER_STATS_JWT_ACTIVE', false),

    'logs-endpoint' => env('SERVER_LOGS_ENDPOINT', '/logs'),
    'secure-logs-jwt' => env('SERVER_LOGS_JWT_ACTIVE', false),
];
