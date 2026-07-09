<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Tests;

use Prometheus\Storage\InMemory;
use Rasuvaeff\Yii3MetricsPrometheus\StorageFactory;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(StorageFactory::class)]
final class StorageFactoryTest
{
    #[DataProvider('adapterProvider')]
    public function createsTheRequestedAdapter(string $name, string $expected): void
    {
        Assert::instanceOf((new StorageFactory())->create($name), $expected);
    }

    public static function adapterProvider(): iterable
    {
        yield 'default in-memory' => [StorageFactory::IN_MEMORY, InMemory::class];
        yield 'unknown falls back to in-memory' => ['nope', InMemory::class];

        // 'apcu'/'apc'/'redis' construct promphp adapters that eagerly require the
        // ext-apcu / ext-redis extension, so they are exercised in the (ext-gated)
        // Integration suite, not here.
    }
}
