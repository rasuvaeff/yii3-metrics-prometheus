# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.0.0 — Unreleased

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
