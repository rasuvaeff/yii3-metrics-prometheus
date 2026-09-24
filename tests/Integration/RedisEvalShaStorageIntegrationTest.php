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
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * Real round-trips through the opt-in EVALSHA adapters for both Redis client
 * libraries, including recovery after Redis loses its server-side script cache
 * (restart or SCRIPT FLUSH). Requires REDIS_HOST; the redis case additionally
 * requires ext-redis.
 */
#[Test]
#[CoversNothing]
final class RedisEvalShaStorageIntegrationTest
{
    #[DataProvider('adapterProvider')]
    public function recordsAndRendersAndRecoversAfterScriptFlush(string $adapter): void
    {
        $host = getenv('REDIS_HOST');
        if (!is_string($host) || $host === '') {
            return;
        }

        if ($adapter === StorageFactory::REDIS && !extension_loaded('redis')) {
            return;
        }

        $port = (int) (getenv('REDIS_PORT') ?: 6379);

        $storage = (new StorageFactory())->create($adapter, [
            'evalsha' => true,
            'host' => $host,
            'port' => $port,
            'prefix' => 'yii3_metrics_evalsha_',
        ]);
        $storage->wipeStorage();

        $registry = new CollectorRegistry($storage, registerDefaultMetrics: false);
        $metrics = new MetricRegistry(new PrometheusMeterProvider($registry));
        $metrics->counter('redis_evalsha_probe_total', 'probe')->inc();
        $metrics->counter('redis_evalsha_probe_total')->inc();

        Assert::string((new PrometheusRenderer())->render($registry))->contains('redis_evalsha_probe_total 2');

        // A restart or SCRIPT FLUSH empties the server-side script cache while
        // the adapter keeps writing by SHA. It must recover through the
        // NOSCRIPT fallback (plain EVAL) — including phpredis, which reports
        // the error reply via false + getLastError() instead of an exception.
        $this->flushScripts($adapter, $host, $port);

        $metrics->counter('redis_evalsha_probe_total')->inc();
        $metrics->counter('redis_evalsha_probe_total')->inc();

        Assert::string((new PrometheusRenderer())->render($registry))->contains('redis_evalsha_probe_total 4');

        $storage->wipeStorage();
    }

    public static function adapterProvider(): iterable
    {
        yield 'predis' => [StorageFactory::PREDIS];

        yield 'redis' => [StorageFactory::REDIS];
    }

    private function flushScripts(string $adapter, string $host, int $port): void
    {
        if ($adapter === StorageFactory::REDIS) {
            $redis = new \Redis();
            $redis->connect($host, $port);
            $redis->script('flush');

            return;
        }

        $client = new \Predis\Client(['host' => $host, 'port' => $port]);
        $client->script('flush');
    }
}
