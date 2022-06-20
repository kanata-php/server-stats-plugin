<?php

return [
    'endpoint' => env('SERVER_STATS_ENDPOINT', '/metrics'),
    'secure-metrics-jwt' => env('SERVER_STATS_JWT_ACTIVE', false),
];
