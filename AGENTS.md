# AGENTS.md — yii3-metrics-prometheus

Guidance for AI agents working on this package. Read before changing code.

## What this is

The Prometheus **metrics backend** for `rasuvaeff/yii3-metrics`. It adapts the
core `MetricRegistry` facade onto `promphp/prometheus_client_php`, exposes a
`/metrics` PSR-15 endpoint, and provides multiprocess storage selection plus a
cardinality-sanitizing route resolver.

Namespace: `Rasuvaeff\Yii3MetricsPrometheus`.

Public API: `PrometheusMeterProvider` (implements core `MeterProviderInterface`),
`PrometheusMeter`, `PrometheusCounter` / `PrometheusGauge` / `PrometheusHistogram`
(adapters), `PrometheusRenderer`, `MetricsEndpoint` (PSR-15), `StorageFactory`,
`SanitizingRouteResolver`.

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

## Local build & the path-repo / publish trap

Requires `rasuvaeff/yii3-metrics: ^1.0` — **not on Packagist yet**. Local builds
resolve it via a `repositories` **path** entry (`/repo/yii3-metrics`), so every
Docker command must mount the **monorepo root** as `/repo`:

```bash
docker run --rm -v /home/rasuvaeff/projects/rasuvaeff:/repo \
  -w /repo/yii3-metrics-prometheus composer:2 composer build
```

`make build` (which mounts `/app`) FAILS — the installed
`vendor/rasuvaeff/yii3-metrics` symlinks to `/repo/yii3-metrics`. **Before
publishing:** release the core to Packagist first, then remove the `repositories`
block.

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
  raises `E_USER_WARNING` for that combination (`PHP_SAPI === 'fpm-fcgi'`). Use
  `apcng`/`apcu`/`redis`/`predis`/`pdo`, documented in the README.
- **`Internal\Labels::order()` throws on an undeclared label** (typo guard) and
  renders missing declared labels as empty strings — both covered in
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
