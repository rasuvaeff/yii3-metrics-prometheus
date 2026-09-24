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
 * Real round-trip through the opt-in EVALSHA Predis adapter.
 *
 * Run with REDIS_HOST and a reachable Redis server.
 */
#[Test]
#[CoversNothing]
final class RedisEvalShaStorageIntegrationTest
{
    public function recordsAndRendersViaEvalShaStorage(): void
    {
        $host = getenv('REDIS_HOST');
        if (!is_string($host) || $host === '') {
            return;
        }

        $storage = (new StorageFactory())->create(StorageFactory::PREDIS, [
            'evalsha' => true,
            'host' => $host,
            'port' => (int) (getenv('REDIS_PORT') ?: 6379),
            'prefix' => 'yii3_metrics_evalsha_',
        ]);
        $storage->wipeStorage();

        $registry = new CollectorRegistry($storage, registerDefaultMetrics: false);
        $metrics = new MetricRegistry(new PrometheusMeterProvider($registry));
        $metrics->counter('redis_evalsha_probe_total', 'probe')->inc();
        $metrics->counter('redis_evalsha_probe_total')->inc();

        Assert::string((new PrometheusRenderer())->render($registry))->contains('redis_evalsha_probe_total 2');

        // Redis restarts and SCRIPT FLUSH invalidate the server-side cache while
        // the PHP client still remembers the SHA. The adapter must recover with
        // EVAL for this write and cache the script again afterwards.
        $client = new \Predis\Client([
            'host' => $host,
            'port' => (int) (getenv('REDIS_PORT') ?: 6379),
        ]);
        $client->script('flush');
        $metrics->counter('redis_evalsha_probe_total')->inc();

        Assert::string((new PrometheusRenderer())->render($registry))->contains('redis_evalsha_probe_total 3');

        $storage->wipeStorage();
    }
}
