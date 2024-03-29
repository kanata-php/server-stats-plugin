<?php

use Kanata\Interfaces\KanataPluginInterface;
use Psr\Container\ContainerInterface;
use Kanata\Annotations\Plugin;
use Kanata\Annotations\Description;
use Kanata\Annotations\Author;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use ServerStats\Http\Controllers\ServerStatsController;
use Swoole\Table;

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
            add_filter('error_template', fn($t) => 'stats::ws-error');
        }

        if (is_http_execution() || is_websocket_execution()) {
            $this->register_requests_counter_table();
            $this->register_metrics_endpoints();
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
    private function register_metrics_endpoints()
    {
        add_filter('routes', function($app) {

            // here we cancel any request to home
            if (is_websocket_execution()) {
                $app->get('/', function(Request $request, Response $response) {
                    return view($response, 'stats::ws-error', [
                        'content' => 'Nothing here.',
                    ]);
                })->setName('home');
            }

            // --------------------------------------------------------------
            // metrics
            // --------------------------------------------------------------

            $metricsRoute = $app->get(config('server-stats.endpoint'), [ServerStatsController::class, 'metrics'])->setName('metrics');
            if (
                is_plugin_active('user-authorization')
                && config('server-stats.secure-metrics-jwt')
            ) {
                $metricsRoute->add(new \UserAuthorization\Http\Middlewares\JwtAuthMiddleware);
            }

            // --------------------------------------------------------------
            // logs
            // --------------------------------------------------------------

            $logsRoute = $app->get(config('server-stats.logs-endpoint'), [ServerStatsController::class, 'logs'])->setName('logs');
            if (
                is_plugin_active('user-authorization')
                && config('server-stats.secure-logs-jwt')
            ) {
                $logsRoute->add(new \UserAuthorization\Http\Middlewares\JwtAuthMiddleware);
            }

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
