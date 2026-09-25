<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Tests\Integration;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\RedisClients\RedisClientException;
use Rasuvaeff\Yii3Metrics\MetricRegistry;
use Rasuvaeff\Yii3MetricsPrometheus\Internal\PhpRedisEvalShaClient;
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
 * (restart or SCRIPT FLUSH). Besides the rendered values, the test reads
 * `INFO commandstats` deltas to prove the writes really address the scripts by
 * SHA-1 (`cmdstat_evalsha`) and that a lost script cache falls back to plain
 * `EVAL` (`cmdstat_eval`) instead of dropping writes. Requires REDIS_HOST; the
 * redis case additionally requires ext-redis.
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

        $evalShaCalls = fn(): int => $this->commandCalls($adapter, $host, $port, 'evalsha');
        $evalCalls = fn(): int => $this->commandCalls($adapter, $host, $port, 'eval');

        $evalShaBefore = $evalShaCalls();

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

        $evalShaMid = $evalShaCalls();
        $evalMid = $evalCalls();

        Assert::true($evalShaMid > $evalShaBefore, 'expected EVALSHA commands on the wire');

        // A restart or SCRIPT FLUSH empties the server-side script cache while
        // the adapter keeps writing by SHA. It must recover through the
        // NOSCRIPT fallback (plain EVAL) — including phpredis, which reports
        // the error reply via false + getLastError() instead of an exception.
        $this->flushScripts($adapter, $host, $port);

        $metrics->counter('redis_evalsha_probe_total')->inc();
        $metrics->counter('redis_evalsha_probe_total')->inc();

        Assert::string((new PrometheusRenderer())->render($registry))->contains('redis_evalsha_probe_total 4');

        Assert::true($evalCalls() > $evalMid, 'expected an EVAL fallback after the script cache was flushed');
        Assert::true($evalShaCalls() > $evalShaMid, 'expected EVALSHA writes to resume after the fallback');

        $storage->wipeStorage();
    }

    /**
     * A failing fallback EVAL must stay visible: after SCRIPT FLUSH the
     * EVALSHA probe answers NOSCRIPT and the retry runs the script body, whose
     * error reply phpredis surfaces as a raw RedisException or through
     * getLastError() — the decorator reports both as RedisClientException.
     */
    public function throwsWhenTheFallbackEvalFails(): void
    {
        $host = getenv('REDIS_HOST');
        if (!is_string($host) || $host === '' || !extension_loaded('redis')) {
            return;
        }

        $port = (int) (getenv('REDIS_PORT') ?: 6379);
        $this->flushScripts(StorageFactory::REDIS, $host, $port);

        $client = new PhpRedisEvalShaClient(['host' => $host, 'port' => $port]);
        $client->ensureOpenConnection();

        try {
            $client->eval('return redis.error_reply("fallback probe failed")', [], 0);
            Assert::fail('expected the fallback EVAL error');
        } catch (RedisClientException $exception) {
            Assert::string($exception->getMessage())->contains('fallback probe failed');
        }
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

    /**
     * Cumulative `calls` of a Redis command from `INFO commandstats`. Predis
     * wraps the section in a `Commandstats` key and keeps the raw
     * `calls=N,usec=...` strings, phpredis returns the entries flat (parsed
     * into arrays) — both shapes are handled.
     */
    private function commandCalls(string $adapter, string $host, int $port, string $command): int
    {
        if ($adapter === StorageFactory::REDIS) {
            $redis = new \Redis();
            $redis->connect($host, $port);
            /** @var array<string, mixed> $info */
            $info = $redis->info('commandstats');
        } else {
            $client = new \Predis\Client(['host' => $host, 'port' => $port]);
            /** @var array<string, mixed> $info */
            $info = $client->info('commandstats');
        }

        $key = 'cmdstat_' . $command;
        $entry = $info[$key] ?? null;
        if ($entry === null) {
            foreach ($info as $value) {
                if (is_array($value) && array_key_exists($key, $value)) {
                    $entry = $value[$key];

                    break;
                }
            }
        }

        if (is_array($entry)) {
            return (int) ($entry['calls'] ?? 0);
        }

        if (is_string($entry) && preg_match('/(?:^|,)calls=(\d+)/', $entry, $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }
}
