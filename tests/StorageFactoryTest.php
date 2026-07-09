<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Tests;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\PDO as PdoAdapter;
use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;
use Rasuvaeff\Yii3MetricsPrometheus\StorageFactory;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(StorageFactory::class)]
final class StorageFactoryTest
{
    public function createsInMemoryByDefault(): void
    {
        Assert::instanceOf((new StorageFactory())->create(), InMemory::class);
    }

    public function createsPdoAdapterOverSqlite(): void
    {
        $adapter = (new StorageFactory())->create(StorageFactory::PDO, ['dsn' => 'sqlite::memory:']);

        Assert::instanceOf($adapter, PdoAdapter::class);

        // Round-trip: the adapter actually stores and renders a sample.
        $registry = new CollectorRegistry($adapter, false);
        $registry->getOrRegisterCounter('', 'orders_total', 'Orders placed', ['channel'])
            ->incBy(2, ['web']);

        $text = (new RenderTextFormat())->render($registry->getMetricFamilySamples());
        Assert::string($text)->contains('orders_total{channel="web"} 2');
    }

    // 'apcu'/'apc'/'redis' construct promphp adapters that eagerly require the
    // ext-apcu / ext-redis extension, so they are exercised in the (ext-gated)
    // Integration suite, not here.

    #[DataProvider('invalidProvider')]
    public function throwsOnInvalidConfiguration(string $adapter, array $options, string $needle): void
    {
        try {
            (new StorageFactory())->create($adapter, $options);
            Assert::fail('expected an InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains($needle);
        }
    }

    public static function invalidProvider(): iterable
    {
        yield 'unknown adapter' => ['memcached', [], 'Unknown storage adapter "memcached"'];
        yield 'pdo without dsn' => [StorageFactory::PDO, [], '"dsn"'];
        yield 'pdo with empty dsn' => [StorageFactory::PDO, ['dsn' => ''], '"dsn"'];
    }
}
