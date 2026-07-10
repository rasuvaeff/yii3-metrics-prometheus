<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus;

use Prometheus\Gauge;
use Rasuvaeff\Yii3Metrics\LabelSet;
use Rasuvaeff\Yii3Metrics\UpDownCounterInterface;
use Rasuvaeff\Yii3MetricsPrometheus\Internal\Labels;

/**
 * Adapts the core {@see UpDownCounterInterface} onto a promphp gauge — the
 * Prometheus model for values that go up and down. State lives in the shared
 * storage adapter, so deltas from every worker aggregate on one series.
 *
 * @api
 */
final readonly class PrometheusUpDownCounter implements UpDownCounterInterface
{
    /**
     * @param list<string> $labelNames
     */
    public function __construct(
        private Gauge $gauge,
        private array $labelNames,
    ) {}

    #[\Override]
    public function add(float $delta, LabelSet $labels = new LabelSet()): void
    {
        $this->gauge->incBy($delta, Labels::order($labels, $this->labelNames));
    }
}
