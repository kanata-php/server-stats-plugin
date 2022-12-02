<?php

namespace ServerStats\Services;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ServerStats as MainServerStats;
use Swoole\Http\Server;
use Swoole\WebSocket\Server as WebSocketServer;

class ServerStats
{
    /**
     * Reference: https://gist.github.com/johanjanssens/860991e996c59fb70876288ec760d096
     *
     * Extracted from https://github.com/openswoole/swoole-src/blob/f191c0b0a98e9b97f5c81d4877f450c863e6c36d/ext-src/php_swoole_library.h#L6999
     *
     * Changes:
     *
     * - Added 'service' label
     * - Added additional label support
     *
     * Additional metrics:
     *
     * - openswoole_top_classes_total
     * - openswoole_event_workers_cpu_average
     * - openswoole_task_workers_cpu_average
     * - openswoole_user_workers_cpu_average
     * - openswoole_cpu_average
     *
     * Todo
     * - Refactor use shell_exec() to call ps for cpu stats
     *
     * Updates
     * - 16/06/2022: Replace ${var} with {$var} for forward PHP 8.2 compat
     *
     * @param Server|WebSocketServer $service
     * @param string $service
     * @param array $labels
     * @return string
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function getMetrics(
        Server|WebSocketServer $server,
        string $service = 'openswoole',
        array $labels = [],
        string $mode = 'default',
    ): string {
        if ('json' === $mode) {
            return self::getJsonMetrics($server, $service, $labels);
        }

        return self::getOpenMetrics($server, $service, $labels);
    }

    private static function getOpenMetrics(
        Server|WebSocketServer $server,
        string $service = 'openswoole',
        array $labels = [],
    ): string {
        $table = container()->get(MainServerStats::METRICS_SKIP_TABLE);
        $metrics_request_count = $table->get('metrics', 'counter');

        $stats = $server->stats();
        $labels['service'] = strtolower($service);

        //Serialise the labels
        foreach($labels as $key => $value) {
            $labels[$key] = $key.'="'.$value.'"';
        }

        $labels = implode(',', $labels);
        // $cpu_total = 0.0;

        $event_workers = [];
        $event_workers[] = "# TYPE openswoole_event_workers_start_time gauge";
        foreach ($stats['event_workers'] as $stat) {
            $event_workers[] = "openswoole_event_workers_start_time{{$labels},worker_id=\"{$stat['worker_id']}\"} {$stat['start_time']}";
        }

        $event_workers[] = "# TYPE openswoole_event_workers_start_seconds gauge";
        foreach ($stats['event_workers'] as $stat) {
            $event_workers[] = "openswoole_event_workers_start_seconds{{$labels},worker_id=\"{$stat['worker_id']}\"} {$stat['start_seconds']}";
        }

        $event_workers[] = "# TYPE openswoole_event_workers_dispatch_count gauge";
        foreach ($stats['event_workers'] as $stat) {
            $w_metrics_request_count = $table->get('metrics-' . $stat['worker_id'], 'counter');
            $stat['dispatch_count'] = (int) $stat['dispatch_count'] - (int) $w_metrics_request_count;
            $stat['dispatch_count'] = $stat['dispatch_count'] < 0 ? 0 : $stat['dispatch_count'];

            $event_workers[] = "openswoole_event_workers_dispatch_count{{$labels},worker_id=\"{$stat['worker_id']}\"} {$stat['dispatch_count']}";
        }

        $event_workers[] = "# TYPE openswoole_event_workers_request_count gauge";
        foreach ($stats['event_workers'] as $stat) {
            $w_metrics_request_count = $table->get('metrics-' . $stat['worker_id'], 'counter');
            $stat['request_count'] = (int) $stat['request_count'] - (int) $w_metrics_request_count;
            $stat['request_count'] = $stat['request_count'] < 0 ? 0 : $stat['request_count'];

            $event_workers[] = "openswoole_event_workers_request_count{{$labels},worker_id=\"{$stat['worker_id']}\"} {$stat['request_count']}";
        }

        // $event_workers[] = "# TYPE openswoole_event_workers_cpu gauge";
        // foreach ($stats['event_workers'] as $stat) {
        //     $pid = $stat['pid'];
        //     $cpu_total +=  $cpu = (float) trim(shell_exec("ps -p $pid -o %cpu | tail -n +2"));
        //     $event_workers[] = "openswoole_event_workers_cpu_average{{$labels},worker_id=\"{$stat['worker_id']}\"} {$cpu}";
        // }

        $task_workers = [];
        $task_workers[] = "# TYPE openswoole_task_workers_start_time gauge";
        foreach ($stats['task_workers'] as $stat) {
            $task_workers[] = "openswoole_task_workers_start_time{{$labels},worker_id=\"{$stat['worker_id']}\"} {$stat['start_time']}";
        }

        $task_workers[] = "# TYPE openswoole_task_workers_start_seconds gauge";
        foreach ($stats['task_workers'] as $stat) {
            $task_workers[] = "openswoole_task_workers_start_seconds{{$labels},worker_id=\"{$stat['worker_id']}\"} {$stat['start_seconds']}";
        }
        
        // $event_workers[] = "# TYPE openswoole_task_workers_cpu gauge";
        // foreach ($stats['task_workers'] as $stat) {
        //     $pid = $stat['pid'];
        //     $cpu_total +=  $cpu = (float) trim(shell_exec("ps -p $pid -o %cpu | tail -n +2"));
        //     $event_workers[] = "openswoole_task_workers_cpu_average{{$labels},worker_id=\"{$stat['worker_id']}\"} {$cpu}";
        // }

        $user_workers = [];
        $user_workers[] = "# TYPE openswoole_user_workers_start_time gauge";
        foreach ($stats['user_workers'] as $stat) {
            $user_workers[] = "openswoole_user_workers_start_time{{$labels},worker_id=\"{$stat['worker_id']}\"} {$stat['start_time']}";
        }

        $user_workers[] = "# TYPE openswoole_user_workers_start_seconds gauge";
        foreach ($stats['user_workers'] as $stat) {
            $user_workers[] = "openswoole_user_workers_start_seconds{{$labels},worker_id=\"{$stat['worker_id']}\"} {$stat['start_seconds']}";
        }

        // $event_workers[] = "# TYPE openswoole_user_workers_cpu gauge";
        // foreach ($stats['user_workers'] as $stat) {
        //     $pid = $stat['pid'];
        //     $cpu_total +=  $cpu = (float) trim(shell_exec("ps -p $pid -o %cpu | tail -n +2"));
        //     $event_workers[] = "openswoole_user_workers_cpu_average{{$labels},worker_id=\"{$stat['worker_id']}\"} {$cpu}";
        // }

        $classes = [];
        $classes[] = "# TYPE openswoole_top_classes_total counter";
        foreach ($stats['top_classes'] as $name => $value) {
            $name = addslashes($name);
            $classes[] = "openswoole_top_classes_total{{$labels},class_name=\"{$name}\"} {$value}";
        }

        // $pid = $server->getMasterPid();
        // $cpu_total += (float) trim(shell_exec("ps -p $pid -o %cpu | tail -n +2"));

        // $pid = $server->getManagerPid();
        // $cpu_total += (float) trim(shell_exec("ps -p $pid -o %cpu | tail -n +2"));

        // BEGIN: adjust requests total

        $stats['requests_total'] = (int) $stats['requests_total'] - (int) $metrics_request_count;
        $stats['requests_total'] = $stats['requests_total'] < 0 ? 0 : $stats['requests_total'];

        $stats['connections_accepted'] = (int) $stats['connections_accepted'] - (int) $metrics_request_count;
        $stats['connections_accepted'] = $stats['connections_accepted'] < 0 ? 0 : $stats['connections_accepted'];

        // END: adjust requests total

        $metrics = [
            "# TYPE openswoole_info gauge",
            "openswoole_info{{$labels},version=\"{$stats['version']}\"} 1",
            "# TYPE openswoole_up gauge",
            "openswoole_up{{$labels}} {$stats['up']}",
            "# TYPE openswoole_reactor_num gauge",
            "openswoole_reactor_threads_num{{$labels}} {$stats['reactor_threads_num']}",
            "# TYPE openswoole_requests counter",
            "openswoole_requests_total{{$labels}} {$stats['requests_total']}",
            "# TYPE openswoole_start_time gauge",
            "openswoole_start_time{{$labels}} {$stats['start_time']}",
            "# TYPE openswoole_max_conn gauge",
            "openswoole_max_conn{{$labels}} {$stats['max_conn']}",
            "# TYPE openswoole_coroutine_num gauge",
            "openswoole_coroutine_num{{$labels}} {$stats['coroutine_num']}",
            "# TYPE openswoole_start_seconds gauge",
            "openswoole_start_seconds{{$labels}} {$stats['start_seconds']}",
            "# TYPE openswoole_workers_total gauge",
            "openswoole_workers_total{{$labels}} {$stats['workers_total']}",
            "# TYPE openswoole_workers_idle gauge",
            "openswoole_workers_idle{{$labels}} {$stats['workers_idle']}",
            "# TYPE openswoole_task_workers_total gauge",
            "openswoole_task_workers_total{{$labels}} {$stats['task_workers_total']}",
            "# TYPE openswoole_task_workers_idle gauge",
            "openswoole_task_workers_idle{{$labels}} {$stats['task_workers_idle']}",
            "# TYPE openswoole_user_workers_total gauge",
            "openswoole_user_workers_total{{$labels}} {$stats['user_workers_total']}",
            "# TYPE openswoole_dispatch_total gauge",
            "openswoole_dispatch_total{{$labels}} {$stats['dispatch_total']}",
            "# TYPE openswoole_connections_accepted gauge",
            "openswoole_connections_accepted{{$labels}} {$stats['connections_accepted']}",
            "# TYPE openswoole_connections_active gauge",
            "openswoole_connections_active{{$labels}} {$stats['connections_active']}",
            "# TYPE openswoole_connections_closed gauge",
            "openswoole_connections_closed{{$labels}} {$stats['connections_closed']}",
            "# TYPE openswoole_reload_count gauge",
            "openswoole_reload_count{{$labels}} {$stats['reload_count']}",
            "# TYPE openswoole_reload_last_time gauge",
            "openswoole_reload_last_time{{$labels}} {$stats['reload_last_time']}",
            "# TYPE openswoole_worker_vm_object_num gauge",
            "openswoole_worker_vm_object_num{{$labels}} {$stats['worker_vm_object_num']}",
            "# TYPE openswoole_worker_vm_resource_num gauge",
            "openswoole_worker_vm_resource_num{{$labels}} {$stats['worker_vm_resource_num']}",
            "# TYPE openswoole_worker_memory_usage gauge",
            "openswoole_worker_memory_usage{{$labels}} {$stats['worker_memory_usage']}",
            // "# TYPE openswoole_cpu_average gauge",
            // "openswoole_cpu_average{{$labels}} {$cpu_total}",
        ];

        $metrics = array_merge($metrics, $event_workers, $task_workers, $user_workers, $classes);
        $metrics[] =  "# EOF";

        return implode("\n", $metrics);
    }

    private static function getJsonMetrics(
        Server|WebSocketServer $server,
        string $service = 'openswoole',
        array $labels = [],
    ): string {
        $table = container()->get(MainServerStats::METRICS_SKIP_TABLE);
        $metrics_request_count = $table->get('metrics', 'counter');

        $stats = $server->stats();
        $labels['service'] = strtolower($service);

        //Serialise the labels
        foreach($labels as $key => $value) {
            if (!isset($labels[$key])) {
                $labels[$key] = [];
            }
            $labels[$key] = $value;
        }

        $event_workers = [];
        
        $event_workers['openswoole_event_workers_start_time'] = [];
        foreach ($stats['event_workers'] as $stat) {
            $event_workers['openswoole_event_workers_start_time'][] = [
                'labels' => $labels,
                'worker_id' => $stat['worker_id'],
                'key' => 'start_time',
                'value' => $stat['start_time'],
            ];
        }

        $event_workers['openswoole_event_workers_start_seconds'] = [];
        foreach ($stats['event_workers'] as $stat) {
            $event_workers['openswoole_event_workers_start_seconds'][] = [
                'labels' => $labels,
                'worker_id' => $stat['worker_id'],
                'key' => 'start_seconds',
                'value' => $stat['start_seconds'],
            ];
        }

        $event_workers['openswoole_event_workers_dispatch_count'] = [];
        foreach ($stats['event_workers'] as $stat) {
            $w_metrics_request_count = $table->get('metrics-' . $stat['worker_id'], 'counter');
            $stat['dispatch_count'] = (int) $stat['dispatch_count'] - (int) $w_metrics_request_count;
            $stat['dispatch_count'] = $stat['dispatch_count'] < 0 ? 0 : $stat['dispatch_count'];

            $event_workers['openswoole_event_workers_dispatch_count'] = [
                'labels' => $labels,
                'worker_id' => $stat['worker_id'],
                'key' => 'dispatch_count',
                'value' => $stat['dispatch_count'],
            ];
        }

        $event_workers['openswoole_event_workers_request_count'] = [];
        foreach ($stats['event_workers'] as $stat) {
            $w_metrics_request_count = $table->get('metrics-' . $stat['worker_id'], 'counter');
            $stat['request_count'] = (int) $stat['request_count'] - (int) $w_metrics_request_count;
            $stat['request_count'] = $stat['request_count'] < 0 ? 0 : $stat['request_count'];

            $event_workers['openswoole_event_workers_request_count'] = [
                'labels' => $labels,
                'worker_id' => $stat['worker_id'],
                'key' => 'request_count',
                'value' => $stat['request_count'],
            ];
        }

        $task_workers = [];
        
        $task_workers['openswoole_task_workers_start_time'] = [];
        foreach ($stats['task_workers'] as $stat) {
            $task_workers['openswoole_task_workers_start_time'][] = [
                'labels' => $labels,
                'key' => 'start_time',
                'worker_id' => $stat['worker_id'],
                'value' => $stat['start_time'],
            ];
        }

        $task_workers['openswoole_task_workers_start_seconds'] = [];
        foreach ($stats['task_workers'] as $stat) {
            $task_workers['openswoole_task_workers_start_seconds'][] = [
                'labels' => $labels,
                'key' => 'start_seconds',
                'worker_id' => $stat['worker_id'],
                'value' => $stat['start_seconds'],
            ];
        }

        $user_workers = [];
        
        $user_workers['openswoole_user_workers_start_time'] = [];
        foreach ($stats['user_workers'] as $stat) {
            $user_workers['openswoole_user_workers_start_time'][] = [
                'labels' => $labels,
                'worker_id' => $stat['worker_id'],
                'key' => 'start_time',
                'value' => $stat['start_time'],
            ];
        }

        $user_workers['openswoole_user_workers_start_seconds'] = [];
        foreach ($stats['user_workers'] as $stat) {
            $user_workers['openswoole_user_workers_start_seconds'][] = [
                'labels' => $labels,
                'worker_id' => $stat['worker_id'],
                'key' => 'start_seconds',
                'value' => $stat['start_seconds'],
            ];
        }

        $classes = [];
        $classes['openswoole_top_classes_total'] = [];
        foreach ($stats['top_classes'] as $name => $value) {
            $name = addslashes($name);
            $classes['openswoole_top_classes_total'][] = [
                'labels' => $labels,
                'key' => 'version',
                'class_name' => $name,
                'value' => $value,
            ];
        }

        $stats['requests_total'] = (int) $stats['requests_total'] - (int) $metrics_request_count;
        $stats['requests_total'] = $stats['requests_total'] < 0 ? 0 : $stats['requests_total'];

        $stats['connections_accepted'] = (int) $stats['connections_accepted'] - (int) $metrics_request_count;
        $stats['connections_accepted'] = $stats['connections_accepted'] < 0 ? 0 : $stats['connections_accepted'];

        $metrics = [
            "openswoole_info" => [
                'labels' => $labels,
                'key' => 'version',
                'value' => $stats['version'],
            ],
            "openswoole_up" => [
                'labels' => $labels,
                'key' => 'up',
                'value' => $stats['up'],
            ],
            "openswoole_reactor_num" => [
                'labels' => $labels,
                'key' => 'reactor_threads_num',
                'value' => $stats['reactor_threads_num'],
            ],
            "openswoole_requests" => [
                'labels' => $labels,
                'key' => 'requests_total',
                'value' => $stats['requests_total'],
            ],
            "openswoole_start_time" => [
                'labels' => $labels,
                'key' => 'start_time',
                'value' => $stats['start_time'],
            ],
            "openswoole_max_conn" => [
                'labels' => $labels,
                'key' => 'max_conn',
                'value' => $stats['max_conn'],
            ],
            "openswoole_coroutine_num" => [
                'labels' => $labels,
                'key' => 'coroutine_num',
                'value' => $stats['coroutine_num'],
            ],
            "openswoole_start_seconds" => [
                'labels' => $labels,
                'key' => 'start_seconds',
                'value' => $stats['start_seconds'],
            ],
            "openswoole_workers_total" => [
                'labels' => $labels,
                'key' => 'workers_total',
                'value' => $stats['workers_total'],
            ],
            "openswoole_workers_idle" => [
                'labels' => $labels,
                'key' => 'workers_idle',
                'value' => $stats['workers_idle'],
            ],
            "openswoole_task_workers_total" => [
                'labels' => $labels,
                'key' => 'task_workers_total',
                'value' => $stats['task_workers_total'],
            ],
            "openswoole_task_workers_idle" => [
                'labels' => $labels,
                'key' => 'task_workers_idle',
                'value' => $stats['task_workers_idle'],
            ],
            "openswoole_user_workers_total" => [
                'labels' => $labels,
                'key' => 'user_workers_total',
                'value' => $stats['user_workers_total'],
            ],
            "openswoole_dispatch_total" => [
                'labels' => $labels,
                'key' => 'dispatch_total',
                'value' => $stats['dispatch_total'],
            ],
            "openswoole_connections_accepted" => [
                'labels' => $labels,
                'key' => 'connections_accepted',
                'value' => $stats['connections_accepted'],
            ],
            "openswoole_connections_active" => [
                'labels' => $labels,
                'key' => 'connections_active',
                'value' => $stats['connections_active'],
            ],
            "openswoole_connections_closed" => [
                'labels' => $labels,
                'key' => 'connections_closed',
                'value' => $stats['connections_closed'],
            ],
            "openswoole_reload_count" => [
                'labels' => $labels,
                'key' => 'reload_count',
                'value' => $stats['reload_count'],
            ],
            "openswoole_reload_last_time" => [
                'labels' => $labels,
                'key' => 'reload_last_time',
                'value' => $stats['reload_last_time'],
            ],
            "openswoole_worker_vm_object_num" => [
                'labels' => $labels,
                'key' => 'worker_vm_object_num',
                'value' => $stats['worker_vm_object_num'],
            ],
            "openswoole_worker_vm_resource_num" => [
                'labels' => $labels,
                'key' => 'worker_vm_resource_num',
                'value' => $stats['worker_vm_resource_num'],
            ],
            "openswoole_worker_memory_usage" => [
                'labels' => $labels,
                'key' => 'worker_memory_usage',
                'value' => $stats['worker_memory_usage'],
            ],
        ];

        $metrics = array_merge($metrics, $event_workers, $task_workers, $user_workers, $classes);

        return json_encode($metrics);
    }
}