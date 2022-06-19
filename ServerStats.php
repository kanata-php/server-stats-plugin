<?php

use Kanata\Interfaces\KanataPluginInterface;
use Psr\Container\ContainerInterface;
use Kanata\Annotations\Plugin;
use Kanata\Annotations\Description;
use Kanata\Annotations\Author;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Swoole\Table;
use WsServerStats\Services\ServerStats as ServerStatsService;

/**
 * @Plugin(name="ServerStats")
 * @Description(value="Serve Stats about Kanata Server")
 * @Author(name="Savio Resende",email="savio@savioresende.com")
 */

class ServerStats implements KanataPluginInterface
{
    const METRICS_SKIP_TABLE = 'metrics-skip-table';

    protected ContainerInterface $container;

    protected Table $serverStatsTable;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return void
     */
    public function start(): void
    {
        if (!is_http_execution()) {
            return;
        }

        $this->register_requests_counter_table();
        $this->register_metrics_endpoint();
    }

    private function register_metrics_endpoint()
    {
        add_filter('routes', function($app) {
            $app->get(config('server-stats.endpoint'), function(Request $request, Response $response) {
                /** @var Table $table */
                $table = container()->get(self::METRICS_SKIP_TABLE);
                $table->incr('metrics', 'counter');

                $httpServerStats = ServerStatsService::getMetrics();
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
