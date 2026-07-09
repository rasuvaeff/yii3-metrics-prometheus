<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus;

use Prometheus\CollectorRegistry;
use Rasuvaeff\Yii3Metrics\CounterInterface;
use Rasuvaeff\Yii3Metrics\GaugeInterface;
use Rasuvaeff\Yii3Metrics\HistogramInterface;
use Rasuvaeff\Yii3Metrics\MeterInterface;

/**
 * Meter backed by a promphp {@see CollectorRegistry}. Instruments are memoized by
 * name (matching the core contract); the first registration's help and label
 * names win.
 *
 * @api
 */
final class PrometheusMeter implements MeterInterface
{
    /** @var array<string, PrometheusCounter> */
    private array $counters = [];

    /** @var array<string, PrometheusGauge> */
    private array $gauges = [];

    /** @var array<string, PrometheusHistogram> */
    private array $histograms = [];

    public function __construct(
        private readonly CollectorRegistry $registry,
        private readonly string $namespace = '',
    ) {}

    #[\Override]
    public function counter(string $name, string $help = '', array $labelNames = []): CounterInterface
    {
        return $this->counters[$name] ??= new PrometheusCounter(
            $this->registry->getOrRegisterCounter($this->namespace, $name, $help, $labelNames),
            $labelNames,
        );
    }

    #[\Override]
    public function gauge(string $name, string $help = '', array $labelNames = []): GaugeInterface
    {
        return $this->gauges[$name] ??= new PrometheusGauge(
            $this->registry->getOrRegisterGauge($this->namespace, $name, $help, $labelNames),
            $labelNames,
        );
    }

    #[\Override]
    public function histogram(
        string $name,
        string $help = '',
        array $labelNames = [],
        array $buckets = [],
    ): HistogramInterface {
        return $this->histograms[$name] ??= new PrometheusHistogram(
            $this->registry->getOrRegisterHistogram(
                $this->namespace,
                $name,
                $help,
                $labelNames,
                $buckets === [] ? null : $buckets,
            ),
            $labelNames,
        );
    }
}
