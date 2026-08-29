# rasuvaeff/yii3-metrics-prometheus

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-metrics-prometheus.svg)](https://packagist.org/packages/rasuvaeff/yii3-metrics-prometheus)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-metrics-prometheus.svg)](https://packagist.org/packages/rasuvaeff/yii3-metrics-prometheus)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-metrics-prometheus/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-metrics-prometheus/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-metrics-prometheus/static-analysis.yml?branch=master)](https://github.com/rasuvaeff/yii3-metrics-prometheus/actions)
[![Psalm Level](https://shepherd.dev/github/rasuvaeff/yii3-metrics-prometheus/level.svg)](https://shepherd.dev/github/rasuvaeff/yii3-metrics-prometheus)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-metrics-prometheus/php)](https://packagist.org/packages/rasuvaeff/yii3-metrics-prometheus)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-metrics-prometheus.svg)](https://github.com/rasuvaeff/yii3-metrics-prometheus/blob/master/LICENSE.md)
[Русская версия](README.ru.md)

Prometheus backend for [`rasuvaeff/yii3-metrics`](https://github.com/rasuvaeff/yii3-metrics).
It records the core `MetricRegistry` metrics into `promphp/prometheus_client_php`
and exposes them at a `/metrics` endpoint.

> Using an AI coding assistant? [llms.txt](llms.txt) has a compact API reference
> you can pass as context.

## Requirements

- PHP 8.3+
- `rasuvaeff/yii3-metrics` ^1.0
- `promphp/prometheus_client_php` ^2.0
- A shared storage backend for php-fpm: `ext-apcu`, `ext-redis`, `predis/predis`, or a PDO DSN

## Installation

```bash
composer require rasuvaeff/yii3-metrics-prometheus
```

Installing this package binds the swappable `MeterProviderInterface` — the core
`MetricRegistry` now records into Prometheus. Do **not** also bind the provider
yourself (a deliberate `yiisoft/config` `Duplicate key`).

## Usage

### Multiprocess storage (required for php-fpm)

`promphp`'s default `InMemory` storage is **per process**, so under php-fpm
`/metrics` would only reflect the worker that served the scrape. Use a shared
adapter via env:

| Runtime | `PROMETHEUS_STORAGE` | Needs |
|---|---|---|
| php-fpm (multiple workers) | `apcng` (recommended), `apcu`, `redis`, `predis`, or `pdo` | `ext-apcu` / `ext-redis` / `predis/predis` / a PDO DSN |
| RoadRunner / Swoole (one long-running process) | `in_memory` | — |
| CLI / tests | `in_memory` | — |

`apcng` is promphp's newer APCu adapter with much cheaper scrape-time collection
on large registries — prefer it over `apcu` for new deployments. `predis` uses
the pure-PHP client (no `ext-redis`).

```php
use Rasuvaeff\Yii3MetricsPrometheus\StorageFactory;

$adapter = (new StorageFactory())->create('apcng');
// pure-PHP Redis client (no ext-redis):
$adapter = (new StorageFactory())->create('predis', ['host' => 'redis', 'port' => 6379]);
// or, without apcu/redis (MySQL, PostgreSQL, SQLite):
$adapter = (new StorageFactory())->create('pdo', [
    'dsn' => 'mysql:host=db;dbname=app',
    'username' => 'app',
    'password' => getenv('DB_PASSWORD'),
]);
```

An unknown adapter name throws (no silent fallback), and selecting `in_memory`
under php-fpm is **reported** — a scrape that silently shows one worker's
counters is worse than a visible note. The report goes to the application's PSR-3
logger (the package DI passes it in), or to `error_log()` when there is none.

> It is deliberately **not** a PHP warning. `yiisoft/error-handler` converts
> warnings into `ErrorException`, so a `trigger_error()` here would make the
> shipped default configuration (`in_memory` + php-fpm) throw out of the DI
> factory and 500 every request that touches metrics.

### The `/metrics` endpoint

`MetricsEndpoint` (PSR-15) renders the registry as Prometheus text exposition
(`text/plain; version=0.0.4`). Route your `/metrics` path to it — it needs a
PSR-17 `ResponseFactoryInterface`. The handler itself has **no access control**:
it serves the full exposition to any request that reaches it, so restrict the
path at your edge (see [Security](#security)). A sample whose labels no longer
match its metric (possible with a Redis storage) is rendered as a comment
instead of failing the whole scrape.

```php
use Rasuvaeff\Yii3MetricsPrometheus\MetricsEndpoint;

$endpoint = new MetricsEndpoint($collectorRegistry, $responseFactory);
```

### Safe labels (cardinality)

`SanitizingRouteResolver` collapses id-like path segments (`/users/123` →
`/users/:id`, UUIDs → `:uuid`) for the RED middleware's `route` label.

The core's shipped default is `ConstantRouteResolver` — the `route` label is the
constant `(unset)` until the application picks a resolver, because a raw path is
attacker-controlled. This resolver is one of the opt-ins, and it narrows the
**id** case only: arbitrary scanner paths and non-UUID tokens stay unique, so it
neither bounds cardinality nor hides secrets in a path. Prefer the core's
`CurrentRouteResolver` (matched router pattern) when you have a router, or wrap
this one in `BoundedRouteResolver` to cap the series count.

Rebind it in your app config (an app-layer override):

```php
// config/common/di.php
use Rasuvaeff\Yii3Metrics\RouteResolverInterface;
use Rasuvaeff\Yii3MetricsPrometheus\SanitizingRouteResolver;

return [
    RouteResolverInterface::class => SanitizingRouteResolver::class,
];
```

Keep label values low-cardinality — one time series is created per unique label
combination.

Recording with a label name that was **not declared** at registration throws
`InvalidArgumentException` (a typo'd label would otherwise silently record under
an empty value); so does a **declared** label missing from the recording's set —
silently filing it as `""` merged every such observation into one empty-valued
series, the same corruption the undeclared case is rejected for.

### Histogram defaults

A histogram registered without explicit buckets gets the core's
`Buckets::PROMETHEUS_DEFAULTS` (11 bounds, 0.005 s … 10 s) — the same list on
every backend, so bucket assertions in in-memory tests describe the production
schema. promphp's own default layout (14 bounds) is never used. Metric names
and bucket layouts are validated by the same core `Validation` the in-memory
and no-op meters apply.

### Numeric input contract

Recorded amounts must be finite: `inc`/`observe`/`add` and gauge `inc`/`dec`
throw on `NAN`/`±INF`; gauge `set()` allows `±INF` but throws on `NAN`
(promphp has no renderable NaN token). Mirrors the core contract, so the same
code cannot behave differently per backend — and nothing non-finite reaches the
shared, durable storage adapters, where a single unguarded `NAN` would poison
the series total until the storage is flushed.

### Metric namespace

Set `PROMETHEUS_NAMESPACE` (params `namespace`) to prefix every metric:
`checkout_http_server_requests_total`. Empty by default.

### Classes

| Class | Purpose |
|---|---|
| `PrometheusMeterProvider` | core `MeterProviderInterface` over a promphp `CollectorRegistry` |
| `PrometheusMeter` / `PrometheusCounter` / `PrometheusGauge` / `PrometheusUpDownCounter` / `PrometheusHistogram` | adapters |
| `PrometheusRenderer` | render a registry as text exposition |
| `MetricsEndpoint` | PSR-15 `/metrics` handler |
| `StorageFactory` | build the storage adapter (`in_memory`/`apcu`/`apcng`/`redis`/`predis`/`pdo`) |
| `SanitizingRouteResolver` | opt-in low-cardinality route label |

## Security

- Label values are arbitrary — keep ids/tokens out of them; use
  `SanitizingRouteResolver` for the route label.
- `php_info` and other promphp default metrics are disabled
  (`registerDefaultMetrics: false`).
- **Protect `/metrics`**: the exposition reveals routes, traffic, and error
  rates. Restrict it to your scrape network (firewall / ingress allowlist) or
  put basic auth in front — do not expose it publicly.

## Examples

Runnable, server-independent scripts in [`examples/`](examples/). See
[`examples/README.md`](examples/README.md).

### Dependency analysers

This leaf package is selected by the root application through config-plugin and
may legitimately have no class reference in an autoloaded source directory. Keep
the direct dependency: the application, not a core package, selects the backend
or bridge. Scope the Composer Dependency Analyser exception to this package:

```php
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())->ignoreErrorsOnPackage(
    'rasuvaeff/yii3-metrics-prometheus',
    [ErrorType::UNUSED_DEPENDENCY],
);
```

`composer-require-checker` detects used but undeclared symbols, not unused
packages, so this config-only dependency needs no require-checker suppression.

## Development

The core is resolved via a path repository, so run Docker with the **monorepo
root** mounted as `/repo`:

```bash
docker run --rm -v /path/to/monorepo:/repo -w /repo/yii3-metrics-prometheus \
  composer:2 composer build
```

See [AGENTS.md](AGENTS.md).

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
