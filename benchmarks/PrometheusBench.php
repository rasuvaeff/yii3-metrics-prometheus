<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Benchmarks;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Rasuvaeff\Yii3Metrics\LabelSet;
use Rasuvaeff\Yii3Metrics\MetricRegistry;
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusMeterProvider;
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusRenderer;
use Testo\Bench;

final class PrometheusBench
{
    #[Bench(
        callables: [
            'render' => [self::class, 'render'],
        ],
        calls: 1_000,
        iterations: 5,
    )]
    public static function record(): int
    {
        $counter = self::registry()->counter('http_requests_total', '', ['status']);
        $labels = new LabelSet(['status' => '200']);

        $i = 0;

        for (; $i < 50; ++$i) {
            $counter->inc(1.0, $labels);
        }

        return $i;
    }

    public static function render(): int
    {
        $registry = new CollectorRegistry(new InMemory(), false);
        $metrics = new MetricRegistry(new PrometheusMeterProvider($registry));
        $metrics->counter('http_requests_total', '', ['status'])->inc(1.0, new LabelSet(['status' => '200']));

        return strlen((new PrometheusRenderer())->render($registry));
    }

    private static function registry(): MetricRegistry
    {
        return new MetricRegistry(new PrometheusMeterProvider(new CollectorRegistry(new InMemory(), false)));
    }
}
