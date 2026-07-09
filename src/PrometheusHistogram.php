<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus;

use Prometheus\Histogram;
use Rasuvaeff\Yii3Metrics\HistogramInterface;
use Rasuvaeff\Yii3Metrics\LabelSet;
use Rasuvaeff\Yii3MetricsPrometheus\Internal\Labels;

/**
 * Adapts a promphp histogram to the core {@see HistogramInterface}.
 *
 * @api
 */
final readonly class PrometheusHistogram implements HistogramInterface
{
    /**
     * @param list<string> $labelNames
     */
    public function __construct(
        private Histogram $histogram,
        private array $labelNames,
    ) {}

    #[\Override]
    public function observe(float $value, LabelSet $labels = new LabelSet()): void
    {
        $this->histogram->observe($value, Labels::order($labels, $this->labelNames));
    }
}
