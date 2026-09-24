<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Prometheus\CollectorRegistry;
use Prometheus\Exception\StorageException;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\InMemory;
use Rasuvaeff\Yii3Metrics\MetricRegistry;
use Rasuvaeff\Yii3MetricsPrometheus\MetricsEndpoint;
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusMeterProvider;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(MetricsEndpoint::class)]
final class MetricsEndpointTest
{
    public function rendersMetricsAsPrometheusTextPlain(): void
    {
        $registry = new CollectorRegistry(new InMemory(), registerDefaultMetrics: false);
        $metrics = new MetricRegistry(new PrometheusMeterProvider($registry));
        $metrics->counter('hits_total', 'Hits')->inc();

        $factory = new Psr17Factory();
        $endpoint = new MetricsEndpoint($registry, $factory);

        $response = $endpoint->handle($factory->createServerRequest('GET', 'https://x/metrics'));

        Assert::same($response->getStatusCode(), 200);
        Assert::string($response->getHeaderLine('Content-Type'))->contains('text/plain');
        Assert::string($response->getHeaderLine('Content-Type'))->contains('version=0.0.4');
        Assert::string((string) $response->getBody())->contains('hits_total 1');
    }

    public function returns503WhenStorageIsUnavailable(): void
    {
        $registry = new CollectorRegistry(new class implements Adapter {
            public function collect(): array
            {
                throw new StorageException('redis unavailable');
            }

            public function updateSummary(array $data): void {}

            public function updateHistogram(array $data): void {}

            public function updateGauge(array $data): void {}

            public function updateCounter(array $data): void {}

            public function wipeStorage(): void {}
        }, registerDefaultMetrics: false);
        $factory = new Psr17Factory();
        $response = (new MetricsEndpoint($registry, $factory))->handle(
            $factory->createServerRequest('GET', 'https://x/metrics'),
        );

        Assert::same($response->getStatusCode(), 503);
        Assert::same((string) $response->getBody(), 'metrics storage unavailable');
        Assert::string($response->getHeaderLine('Content-Type'))->contains('text/plain');
    }
}
