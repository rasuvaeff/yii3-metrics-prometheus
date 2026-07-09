<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Tests;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Adapter;
use Rasuvaeff\Yii3Metrics\MeterProviderInterface;
use Rasuvaeff\Yii3Metrics\RouteResolverInterface;
use Rasuvaeff\Yii3MetricsPrometheus\MetricsEndpoint;
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusRenderer;
use Rasuvaeff\Yii3MetricsPrometheus\StorageFactory;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Test;

/**
 * `config/*.php` are outside the cs/psalm/testo gate — this guards the wiring:
 * the backend binds the provider, must NOT re-bind the core's route resolver, and
 * its factory chain resolves into a working meter.
 */
#[Test]
#[CoversNothing]
final class ConfigWiringTest
{
    public function backendBindsTheProviderKey(): void
    {
        Assert::array($this->di())->hasKeys(MeterProviderInterface::class);
    }

    public function backendDoesNotRebindTheCoreRouteResolver(): void
    {
        // Re-binding RouteResolverInterface (already bound by the core) would be a
        // `yiisoft/config` Duplicate key. Sanitizing is opt-in at the app layer.
        Assert::array($this->di())->doesNotHaveKeys(RouteResolverInterface::class);
    }

    public function factoryChainResolvesIntoAWorkingMeter(): void
    {
        $di = $this->di();

        $adapter = $di[Adapter::class](new StorageFactory());
        Assert::instanceOf($adapter, Adapter::class);

        $registry = $di[CollectorRegistry::class]($adapter);
        Assert::instanceOf($registry, CollectorRegistry::class);

        $provider = $di[MeterProviderInterface::class]($registry);
        $provider->getMeter()->counter('probe_total', 'probe')->inc();

        Assert::string((new PrometheusRenderer())->render($registry))->contains('probe_total 1');
    }

    public function webConfigBindsTheEndpoint(): void
    {
        /** @var array<string, mixed> $di */
        $di = require dirname(__DIR__) . '/config/di-web.php';

        Assert::array($di)->hasKeys(MetricsEndpoint::class);
    }

    public function paramsAreNamespaced(): void
    {
        /** @var array<string, mixed> $params */
        $params = require dirname(__DIR__) . '/config/params.php';

        Assert::array($params)->hasKeys('rasuvaeff/yii3-metrics-prometheus');
    }

    /**
     * @return array<string, mixed>
     */
    private function di(): array
    {
        /** @var array<string, mixed> $params */
        $params = require dirname(__DIR__) . '/config/params.php';

        /** @var array<string, mixed> $di */
        $di = (static fn(array $params): array => require dirname(__DIR__) . '/config/di.php')($params);

        return $di;
    }
}
