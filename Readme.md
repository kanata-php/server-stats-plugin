
# Server Stats

> Kanata Plugin

Plugin to serve server stats or logs through endoints. The stats are available in the format [OpenMetrics](https://openmetrics.io/) or JSON.

For OpenMetrics on OpenSwoole reference: https://openswoole.com/docs/modules/swoole-server-stats#open-swoole-metrics-for-openmetrics-and-prometheus .


## Usage

### Metrics

Retrieve server stats witht he following request:

```
GET /metrics
```

> **Query Param:** `?mode={string}` (only possible values are `default` - openmetrics - and `json`)
> **Description:** this parameter specify the format of the response.

> Notice that you can customize this endpoint with the environment variable `SERVER_STATS_ENDPOINT`.

### Logs

```
GET /logs
```

> **Query Param:** `?rows={int}`
> **Description:** this parameter specify how many rows should come at the end of the logs file.

> Notice that you can customize this endpoint with the environment variable `SERVER_LOGS_ENDPOINT`.

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

### Endpoints

**Stats Endpoint**

By default this plugin adds the endpoint `/metrics` to your site.

To customize it you just add the environment variable `SERVER_STATS_ENDPOINT=/my-custom-endpoint` to your `.env`.

**Logs Endpoint**

By default this plugin adds the endpoint `/logs` to your site.

To customize it you just add the environment variable `SERVER_LOGS_ENDPOINT=/my-custom-endpoint` to your `.env`.

### Protecting Routes

To protect the `/metrics` route, you'll need the [User Authorization](https://github.com/kanata-php/user-authorization-plugin) plugin. That can be installed with the command:

```shell
php kanata plugin:install user-authorization
```

After that, activate that plugin and initialize it:

```shell
php kanata plugin:activate UserAuthorization && vendor/bin/start-kanata
```

Once that is done, you can generate a token via UI or via the command (available by the User Authorization plugin):

```shell
php kanata token:issue --name="metrics" --email=my@user-email.com
```

Add this token to the header of each request as a JWT Bearer token like this: `Authorization: Bearer {token}`.

**Protecting Metrics Endpoint**

For the Metrics Endpoint to be protected, add the environment variable `SERVER_STATS_JWT_ACTIVE=true`.

**Protecting Logs Endpoint**

For the Metrics Endpoint to be protected, add the environment variable `SERVER_LOGS_JWT_ACTIVE=true`.
