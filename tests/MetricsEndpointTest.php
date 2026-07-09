<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Prometheus\CollectorRegistry;
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
        $registry = new CollectorRegistry(new InMemory(), false);
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
}
