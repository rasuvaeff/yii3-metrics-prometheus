<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Tests;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\PDO as PdoAdapter;
use Prometheus\Storage\Predis;
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

    // 'apcu'/'apc'/'apcng'/'redis' construct promphp adapters that eagerly
    // require the ext-apcu / ext-redis extension, so they are exercised in the
    // (ext-gated) Integration suite, not here.

    public function warnsAboutInMemoryUnderFpm(): void
    {
        $factory = new StorageFactory(sapi: 'fpm-fcgi');
        $warning = null;
        set_error_handler(static function (int $errno, string $message) use (&$warning): bool {
            $warning = $message;

            return true;
        }, E_USER_WARNING);

        try {
            $adapter = $factory->create();
        } finally {
            restore_error_handler();
        }

        Assert::instanceOf($adapter, InMemory::class);
        Assert::string((string) $warning)->contains('per-worker');
    }

    #[DataProvider('noWarningProvider')]
    public function noWarningOutsideTheFpmInMemoryCombination(string $sapi, string $adapter): void
    {
        $factory = new StorageFactory(sapi: $sapi);
        $warning = null;
        set_error_handler(static function (int $errno, string $message) use (&$warning): bool {
            $warning = $message;

            return true;
        }, E_USER_WARNING);

        try {
            $factory->create($adapter, $adapter === StorageFactory::PDO ? ['dsn' => 'sqlite::memory:'] : []);
        } finally {
            restore_error_handler();
        }

        Assert::null($warning);
    }

    public static function noWarningProvider(): iterable
    {
        yield 'cli + in_memory' => ['cli', StorageFactory::IN_MEMORY];
        yield 'fpm + pdo' => ['fpm-fcgi', StorageFactory::PDO];
    }

    #[DataProvider('extensionAdapterProvider')]
    public function extensionAdapterArmsAreWired(string $adapter): void
    {
        // ext-apcu / ext-redis are absent in the build container, so the promphp
        // constructors throw — but NOT the factory's own "Unknown storage
        // adapter" error: the match arm must exist and be reached.
        try {
            (new StorageFactory())->create($adapter);
            Assert::fail('expected the promphp adapter constructor to throw without the extension');
        } catch (InvalidArgumentException $e) {
            Assert::fail('the "' . $adapter . '" arm fell through to the unknown-adapter error: ' . $e->getMessage());
        } catch (\Throwable $e) {
            // ext missing => promphp/PHP error ("...extension..." or "Class
            // \"Redis\" not found") — anything but our unknown-adapter error.
            Assert::false(str_contains($e->getMessage(), 'Unknown storage adapter'));
        }
    }

    public static function extensionAdapterProvider(): iterable
    {
        yield 'apcu' => [StorageFactory::APCU];
        yield 'apc alias' => ['apc'];
        yield 'apcng' => [StorageFactory::APCNG];
        yield 'redis' => [StorageFactory::REDIS];
    }

    public function pdoConfigAppliesDefaultsAndCasts(): void
    {
        $factory = new StorageFactory();

        Assert::same($factory->pdoConfig(['dsn' => 'sqlite::memory:']), [
            'dsn' => 'sqlite::memory:',
            'username' => null,
            'password' => null,
            'prefix' => 'prometheus_',
        ]);

        Assert::same($factory->pdoConfig([
            'dsn' => 'mysql:host=db',
            'username' => 'app',
            'password' => 12345,
            'prefix' => 'custom_',
        ]), [
            'dsn' => 'mysql:host=db',
            'username' => 'app',
            'password' => '12345',
            'prefix' => 'custom_',
        ]);
    }

    public function createsPredisAdapterWithoutConnecting(): void
    {
        // predis/predis connects lazily, so construction is safe without a server.
        $adapter = (new StorageFactory())->create(StorageFactory::PREDIS, ['host' => '127.0.0.1']);

        Assert::instanceOf($adapter, Predis::class);
    }

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
