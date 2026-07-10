# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.0.0 — 2026-07-10

- `StorageFactory`: `apcng` (recommended APCu adapter) and `predis` (pure-PHP
  Redis client) storage adapters; `in_memory` under php-fpm now raises an
  `E_USER_WARNING` instead of silently exposing one worker's counters.
- Recording with an undeclared label name throws `InvalidArgumentException`
  (typo guard); missing declared labels still render as empty strings.
- Optional metric namespace (`PROMETHEUS_NAMESPACE` / params `namespace`)
  prefixing every metric name.
- `PrometheusUpDownCounter` — core `UpDownCounterInterface` rendered as a
  Prometheus gauge (`incBy` deltas into shared storage), aggregating correctly
  across php-fpm workers.

- Prometheus backend for `rasuvaeff/yii3-metrics` over
  `promphp/prometheus_client_php`.
- `PrometheusMeterProvider` / `PrometheusMeter` / `PrometheusCounter` /
  `PrometheusGauge` / `PrometheusHistogram` adapt the core facade; label values
  are positioned by declared name order.
- `PrometheusRenderer` and a PSR-15 `MetricsEndpoint` (`text/plain; version=0.0.4`,
  no `php_info`).
- `StorageFactory` selects `in_memory` / `apcu` / `redis` storage (multiprocess
  storage is mandatory for php-fpm).
- `SanitizingRouteResolver` (opt-in) collapses id-like path segments for the RED
  `route` label.
- `yiisoft/config` wiring binds only the core `MeterProviderInterface`.
- `StorageFactory` gains the `pdo` adapter (MySQL/PostgreSQL/SQLite via a `dsn`
  option) and now throws on an unknown adapter name instead of a silent
  `in_memory` fallback.
