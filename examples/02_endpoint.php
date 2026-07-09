<?php

declare(strict_types=1);

use Nyholm\Psr7\Factory\Psr17Factory;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Rasuvaeff\Yii3Metrics\MetricRegistry;
use Rasuvaeff\Yii3MetricsPrometheus\MetricsEndpoint;
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusMeterProvider;

require __DIR__ . '/../vendor/autoload.php';

$registry = new CollectorRegistry(new InMemory(), false);
$metrics = new MetricRegistry(new PrometheusMeterProvider($registry));
$metrics->counter('hits_total', 'Hits')->inc();

$factory = new Psr17Factory();
$endpoint = new MetricsEndpoint($registry, $factory);

$response = $endpoint->handle($factory->createServerRequest('GET', 'https://demo/metrics'));

printf("HTTP %d\nContent-Type: %s\n\n%s", $response->getStatusCode(), $response->getHeaderLine('Content-Type'), (string) $response->getBody());
