<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Tests\Integration;

use Prometheus\CollectorRegistry;
use Rasuvaeff\Yii3Metrics\MetricRegistry;
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusMeterProvider;
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusRenderer;
use Rasuvaeff\Yii3MetricsPrometheus\StorageFactory;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Test;

/**
 * Real round-trip through the shared APCu storage adapter. Skipped unless the
 * `apcu` extension is loaded and enabled for CLI.
 *
 * Run: `vendor/bin/testo --suite=Integration`
 */
#[Test]
#[CoversNothing]
final class ApcuStorageIntegrationTest
{
    public function recordsAndRendersViaApcuStorage(): void
    {
        if (!extension_loaded('apcu') || ini_get('apc.enable_cli') !== '1') {
            return;
        }

        $storage = (new StorageFactory())->create(StorageFactory::APCU);
        $storage->wipeStorage();

        $registry = new CollectorRegistry($storage, false);
        $metrics = new MetricRegistry(new PrometheusMeterProvider($registry));
        $metrics->counter('apcu_probe_total', 'probe')->inc();

        Assert::string((new PrometheusRenderer())->render($registry))->contains('apcu_probe_total 1');

        $storage->wipeStorage();
    }
}
