# AGENTS.md — yii3-metrics-prometheus

Guidance for AI agents working on this package. Read before changing code.

## What this is

The Prometheus **metrics backend** for `rasuvaeff/yii3-metrics`. It adapts the
core `MetricRegistry` facade onto `promphp/prometheus_client_php`, exposes a
`/metrics` PSR-15 endpoint, and provides multiprocess storage selection plus a
cardinality-sanitizing route resolver.

Namespace: `Rasuvaeff\Yii3MetricsPrometheus`.

Public API: `PrometheusMeterProvider` (implements core `MeterProviderInterface`),
`PrometheusMeter`, `PrometheusCounter` / `PrometheusGauge` /
`PrometheusUpDownCounter` (rendered as a Prometheus gauge) /
`PrometheusHistogram` (adapters), `PrometheusRenderer`, `MetricsEndpoint`
(PSR-15), `StorageFactory`, `SanitizingRouteResolver`.

## DI wiring — the backend side of core+backend

`config/di.php` binds exactly ONE core key: `MeterProviderInterface =>
PrometheusMeterProvider` (plus the promphp `CollectorRegistry` and storage
`Adapter`). It must **never** bind the core `RouteResolverInterface` — the core
already binds `PathRouteResolver`, and a second vendor binding is a
`yiisoft/config` `Duplicate key`. `SanitizingRouteResolver` is opt-in: the app
rebinds `RouteResolverInterface => SanitizingRouteResolver` at the app layer (an
override, not a vendor duplicate). `ConfigWiringTest` guards this.

`CollectorRegistry` is built with `registerDefaultMetrics: false` so promphp's
`php_info` does not leak into the exposition.

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **Label values are positioned by DECLARED name order, never by the sorted
   `LabelSet` map.** promphp stores label names in registration order and expects
   values positionally. `Internal\Labels::order()` iterates the declared
   `$labelNames`. The only reliable guard is asserting rendered
   `RenderTextFormat` text (`{name="value",...}`), not mock call counts — see
   `PrometheusExpositionTest`. A recording counter also rejects a negative
   increment (core contract).
4. **Preserve the public contract.** Update README + tests with any API change.

## Local build

Requires `rasuvaeff/yii3-metrics: ^1.0`, which **is** on Packagist. The
`repositories` path entry this package used before the core was released is
gone, so the ordinary `/app` mount works and there is no monorepo-root
requirement:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
```

`make build` and `bin/build-digest yii3-metrics-prometheus build` both work.

Note that a local build therefore resolves the core from Packagist, **not** from
the working copy next door. When a change depends on unreleased core behaviour,
verify it against the core branch deliberately (a temporary path repository)
rather than assuming the sibling directory is in play. The OTLP backend
`yii3-metrics-otel` still carries a real path repository and does need the
`/repo` mount.

## Invariants & gotchas

- **Exposition test = the key verification, and it is Unit** (`Storage\InMemory`,
  fresh `CollectorRegistry` per test — APC/Redis are global and leak). It proves
  register → record → render (names, label order, cumulative buckets, no
  `php_info`). Real APCu/Redis round-trips live in the (ext-gated) `Integration`
  suite.
- **promphp `APC`/`APCng`/`Redis` adapters throw at construction if the
  extension is missing**, so `StorageFactory::create('apcu'|'apcng'|'redis')` is
  only exercised in the Integration suite; `predis` constructs lazily (predis
  connects on first command) and IS unit-tested. The `pdo` arm IS unit-tested (SQLite `:memory:`;
  `pdo_sqlite` is in the CI extension list of every job). An unknown adapter
  name throws `InvalidArgumentException` — no silent `in_memory` fallback.
- **Multiprocess storage is mandatory for php-fpm** — the `in_memory` adapter is
  per-worker, so `/metrics` would only show the serving worker; `StorageFactory`
  REPORTS that combination (`PHP_SAPI === 'fpm-fcgi'`) through its optional PSR-3
  logger, falling back to `error_log()`. Use `apcng`/`apcu`/`redis`/`predis`/`pdo`,
  documented in the README.
- **Never report a misconfiguration with `trigger_error()`.** `yiisoft/error-handler`
  converts PHP warnings into `ErrorException`, so a warning raised inside a DI
  factory becomes a 500 on every request that touches metrics — the shipped
  default (`in_memory` + php-fpm) did exactly that. `error_log()` bypasses the
  error handler and is the fallback for the same reason.
- **Recording guards must mirror the core's.** `Internal\Amount::assertFinite()`
  on `inc`/`observe`/`add` and gauge `inc`/`dec`; `assertNotNan()` on gauge
  `set()` (promphp renders `+Inf`/`-Inf` but coerces `NAN` to an invalid token
  while raising a PHP warning). The promphp client guards neither, and its
  storage adapters are shared and durable, so an unguarded `NAN` outlives the
  request.
- **`PrometheusMeter` applies the core `Internal\Validation`** (@api since core
  2.1.0): metric-name grammar and bucket layout — promphp's own name regex
  anchors with `$` and accepts a trailing newline the core rejects. A histogram
  without explicit buckets materialises `Buckets::PROMETHEUS_DEFAULTS` (minus
  the trailing `+Inf`, which promphp owns) — promphp's own 14-bound default is
  never used, so the bucket schema matches the core and the OTel backend.
- **`PrometheusRenderer` renders silent.** A sample whose labels no longer
  match its metric (a Redis storage can hold such rows) becomes an `# Error:`
  comment instead of failing the whole scrape; do not "fix" this back to the
  throwing mode. `MetricsEndpoint` itself has no access control — the README's
  Security section owns that contract.
- `PrometheusMeterProvider` memoizes meters per instrumentation scope; the
  meters are thin wrappers and the accumulating state lives in the shared
  registry, so two scopes still record into the same series.
- **`Internal\Labels::order()` throws on BOTH a missing declared label and an
  undeclared one** (typo guard) — a declared label silently recorded as `""`
  merged every such observation into one empty-valued series. The typo case
  surfaces as `Missing label "<declared>"; passed: "<typo'd>"`. The hot path is
  a single `array_key_exists` pass with no `array_keys`/`array_diff`
  allocations; the `array_diff` runs only on the throw path. Covered in
  `PrometheusExpositionTest`.
- Code: `declare(strict_types=1)`, `final readonly class`, `#[\Override]`,
  explicit types.
- **CI workflows are SHA-pinned** (`uses:` → 40-char SHA + `# vN`),
  `permissions: { contents: read }`, `persist-credentials: false`. Verify with
  `zizmor --persona=auditor .github/`.
- `examples/` is part of the public contract: keep scripts runnable.

## When you finish

- Update `README.md` / `examples/`; update `CHANGELOG.md` when releasing.
- Re-run `composer build` (monorepo mount); paste the output.
