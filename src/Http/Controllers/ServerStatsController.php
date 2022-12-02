<?php

namespace ServerStats\Http\Controllers;

use Kanata\Http\Controllers\Controller;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use ServerStats;
use ServerStats\Services\ServerStats as ServerStatsService;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Swoole\Http\Server;
use Swoole\Table;
use Swoole\WebSocket\Server as WebSocketServer;

class ServerStatsController extends Controller
{
    public function metrics(Request $request, Response $response) {
        /** @var Server|WebSocketServer $server */
        $server = container()->get('server');

        $queryParams = $request->getQueryParams();

        /** @var Table $table */
        $table = container()->get(ServerStats::METRICS_SKIP_TABLE);
        $table->incr('metrics', 'counter');
        $table->incr('metrics-' . $server->worker_id, 'counter');

        $httpServerStats = ServerStatsService::getMetrics(
            server: $server,
            mode: array_get($queryParams, 'mode', 'default'),
        );
        $response->getBody()->write($httpServerStats);
        return $response;
    }

    public function logs(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();
        
        $output = '';

        $process = new Process(['tail', '-' . array_get($queryParams, 'rows', 30), storage_path('logs/app.log')]);
        $process->start();

        foreach ($process as $type => $data) {
            if ($process::OUT === $type) {
                $output .= $data . PHP_EOL;
            } else { // $process::ERR === $type
                $output .= 'Failed to read logs: ' . $data . PHP_EOL;
            }
        }
        
        $response->getBody()->write($output);
        
        return $response;
    }
}