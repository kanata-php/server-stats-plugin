<?php

use Kanata\Interfaces\KanataPluginInterface;
use Psr\Container\ContainerInterface;
use Kanata\Annotations\Plugin;
use Kanata\Annotations\Description;
use Kanata\Annotations\Author;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Swoole\Http\Server;
use Swoole\Table;
use ServerStats\Services\ServerStats as ServerStatsService;

/**
 * @Plugin(name="ServerStats")
 * @Description(value="Serve Stats about Kanata Server")
 * @Author(name="Savio Resende",email="savio@savioresende.com")
 */

class ServerStats implements KanataPluginInterface
{
    /**
     * Metrics key of Swoole Table at the container.
     */
    const METRICS_SKIP_TABLE = 'metrics-skip-table';

    protected Table $serverStatsTable;

    public function __construct(
        protected ContainerInterface $container
    ) {}

    /**
     * @return void
     */
    public function start(): void
    {
        if (is_websocket_execution()) {
            $this->register_views();
            add_filter('error_template', fn($t) => 'stats:ws-error');
        }

        if (is_http_execution() || is_websocket_execution()) {
            $this->register_requests_counter_table();
            $this->register_metrics_endpoint();
        }
    }

    /**
     * Register view for WS Server.
     *
     * @return void
     */
    private function register_views(): void
    {
        add_filter('view_folders', function($templates) {
            $templates['stats'] = untrailingslashit(plugin_path('server-stats')) . '/views';
            return $templates;
        });
    }

    /**
     * Register "/metrics" endpoint for WS and Http Servers.
     *
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    private function register_metrics_endpoint()
    {
        add_filter('routes', function($app) {
            if (is_websocket_execution()) {
                $app->get('/', function(Request $request, Response $response) {
                    $content = container()->get('view')->render('stats::ws-error', [
                        'content' => 'Nothing here.',
                    ]);
                    $response->getBody()->write($content);
                    return $response->withStatus(200);
                })->setName('home');
            }

            $app->get(config('server-stats.endpoint'), function(Request $request, Response $response) {
                /** @var Server $server */
                $server = container()->get('server');

                /** @var Table $table */
                $table = container()->get(self::METRICS_SKIP_TABLE);
                $table->incr('metrics', 'counter');
                $table->incr('metrics-' . $server->worker_id, 'counter');

                $httpServerStats = ServerStatsService::getMetrics($server);
                $response->getBody()->write($httpServerStats);
                return $response;
            })->setName('metrics');
            return $app;
        });
    }

    /**
     * This table servers the purpose of avoiding the /metrics
     * request to affect stats.
     *
     * @return void
     */
    private function register_requests_counter_table()
    {
        $table = new Table(1024);
        $table->column('counter', Table::TYPE_INT, 20);
        $table->create();
        container()->set(self::METRICS_SKIP_TABLE, $table);
    }
}
