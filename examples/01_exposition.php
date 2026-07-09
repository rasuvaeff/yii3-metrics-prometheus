<?php

declare(strict_types=1);

use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Rasuvaeff\Yii3Metrics\LabelSet;
use Rasuvaeff\Yii3Metrics\MetricRegistry;
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusMeterProvider;
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusRenderer;

require __DIR__ . '/../vendor/autoload.php';

// In production use StorageFactory::create('apcu'|'redis'); InMemory suits demos.
$registry = new CollectorRegistry(new InMemory(), false);
$metrics = new MetricRegistry(new PrometheusMeterProvider($registry));

$requests = $metrics->counter('http_server_requests_total', 'Total requests', ['method', 'status']);
$requests->inc(1.0, new LabelSet(['method' => 'GET', 'status' => '200']));
$requests->inc(1.0, new LabelSet(['method' => 'GET', 'status' => '200']));

$duration = $metrics->histogram('http_server_request_duration_seconds', 'Duration', ['method'], [0.1, 0.5, 1.0]);
$duration->observe(0.23, new LabelSet(['method' => 'GET']));

echo (new PrometheusRenderer())->render($registry);
