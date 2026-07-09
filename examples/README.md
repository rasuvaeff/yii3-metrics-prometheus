# Examples

Runnable examples for `rasuvaeff/yii3-metrics-prometheus`. Each uses `InMemory`
storage and needs no external services.

| Script | Shows | Needs server? |
|---|---|---|
| `01_exposition.php` | Record metrics, print the Prometheus text exposition | no |
| `02_endpoint.php` | `MetricsEndpoint` producing a `/metrics` PSR-15 response | no |

## Running

From the package directory (the core is resolved via a path repo, so mount the
monorepo root):

```bash
docker run --rm -v /path/to/monorepo:/repo -w /repo/yii3-metrics-prometheus \
  composer:2 php examples/01_exposition.php
```

Or, in an app that installed the package from Packagist, just
`php examples/01_exposition.php`.

For php-fpm, swap `InMemory` for a shared adapter:
`(new StorageFactory())->create('apcu')`.
