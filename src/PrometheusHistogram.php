<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus;

use Prometheus\Histogram;
use Rasuvaeff\Yii3Metrics\HistogramInterface;
use Rasuvaeff\Yii3Metrics\LabelSet;
use Rasuvaeff\Yii3MetricsPrometheus\Internal\Amount;
use Rasuvaeff\Yii3MetricsPrometheus\Internal\Labels;

/**
 * Adapts a promphp histogram to the core {@see HistogramInterface}. A non-finite
 * observation is rejected — promphp would file `NAN` in the `+Inf` bucket and let
 * it poison the sum.
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
        Amount::assertFinite($value);

        $this->histogram->observe($value, Labels::order($labels, $this->labelNames));
    }
}
