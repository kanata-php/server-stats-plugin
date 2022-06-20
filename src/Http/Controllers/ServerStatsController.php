<?php

namespace ServerStats\Http\Controllers;

use Kanata\Http\Controllers\Controller;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use ServerStats;
use ServerStats\Services\ServerStats as ServerStatsService;
use Swoole\Http\Server;
use Swoole\Table;
use Swoole\WebSocket\Server as WebSocketServer;

class ServerStatsController extends Controller
{
    public function metrics(Request $request, Response $response) {
        /** @var Server|WebSocketServer $server */
        $server = container()->get('server');

        /** @var Table $table */
        $table = container()->get(ServerStats::METRICS_SKIP_TABLE);
        $table->incr('metrics', 'counter');
        $table->incr('metrics-' . $server->worker_id, 'counter');

        $httpServerStats = ServerStatsService::getMetrics($server);
        $response->getBody()->write($httpServerStats);
        return $response;
    }
}