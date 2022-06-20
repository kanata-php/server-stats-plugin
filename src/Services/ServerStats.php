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
    public static function getMetrics(Server|WebSocketServer $server, string $service = 'openswoole', array $labels = []): string
    {
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
}