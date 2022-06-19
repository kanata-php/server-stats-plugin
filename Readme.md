
# Server Stats

> Kanata Plugin

Plugin to serve server stats through `/metrics` endoint. The stats are available in the format [OpenMetrics](https://openmetrics.io/).

For OpenMetrics on OpenSwoole reference: https://openswoole.com/docs/modules/swoole-server-stats#open-swoole-metrics-for-openmetrics-and-prometheus .


## Installation

Activate plugin:

```shell
php kanata plugin:activate ServerStats
```

Publish config file:

```shell
php kanata plugin:publish ServerStats config
```

The file to configure will be available at `./config/server-stats.php`


## Config

By default this plugin adds the endpoint `/metrics` to your site. To customize it you just add the environment variable `SERVER_STATS_ENDPOINT=/my-custom-endpoint` to your `.env`.

