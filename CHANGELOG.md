# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.1.0 — 2026-08-22

- **Behaviour change — the `in_memory`-under-php-fpm misconfiguration is no
  longer reported with `trigger_error()`.** `StorageFactory` takes an optional
  PSR-3 logger (new second constructor parameter, bound explicitly in
  `config/di.php`) and logs a warning, falling back to `error_log()` when the
  application has no logger. `yiisoft/error-handler` converts PHP warnings into
  `ErrorException`, so the previous `E_USER_WARNING` made the shipped default
  configuration (`storage = in_memory`, deployed on php-fpm) throw out of the DI
  factory and turn every request that touched metrics into a 500 — the opposite
  of the "visible warning" it was meant to be. `psr/log` moves to `require`.
- Reject non-finite recorded amounts, matching the core contract:
  `PrometheusCounter::inc()`, `PrometheusHistogram::observe()`,
  `PrometheusUpDownCounter::add()` and `PrometheusGauge::inc()`/`dec()` throw
  `InvalidArgumentException` on `NAN` and `±INF`; `PrometheusGauge::set()` still
  accepts `±INF` (promphp renders `+Inf`/`-Inf`) but rejects `NAN`, which promphp
  coerces to the invalid token `NAN` while raising a PHP warning. Neither promphp
  nor the old `$amount < 0` guard stopped `NAN` (every comparison with it is
  false), and a promphp storage adapter is shared and durable, so one poisoned
  recording broke the series until the storage was flushed.
- Document that the core's default `route` label is now the constant `(unset)`,
  and what `SanitizingRouteResolver` does and does not cover.
- Adopt `rasuvaeff/rector-named-literals` and split the CI mutation filter from
  the general one, so documentation and workflow edits stop paying for a full
  mutation run.

## 1.0.2 — 2026-07-25

- Document the exact Composer Dependency Analyser exclusion required when this
  package is consumed only through yiisoft/config metadata.

## 1.0.1 — 2026-07-25

- Reject trailing newlines in route-id sanitization: anchor `ID_PATTERN` and
  `UUID_PATTERN` in `SanitizingRouteResolver` with `\z` instead of `$` (PCRE `$`
  matches before a trailing `\n`). Hygiene only — PSR-7 rejects literal LF in
  URI path upstream, so the smuggling vector is not reachable through the
  standard request pipeline; the change keeps the patterns whole-subject.

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
