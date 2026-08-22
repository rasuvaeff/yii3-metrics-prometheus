<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus;

use Prometheus\Gauge;
use Rasuvaeff\Yii3Metrics\GaugeInterface;
use Rasuvaeff\Yii3Metrics\LabelSet;
use Rasuvaeff\Yii3MetricsPrometheus\Internal\Amount;
use Rasuvaeff\Yii3MetricsPrometheus\Internal\Labels;

/**
 * Adapts a promphp gauge to the core {@see GaugeInterface}. `set()` is an
 * absolute write: `±INF` is fine (promphp renders `+Inf` / `-Inf`) but `NAN` is
 * rejected — it has no renderable token. `inc()`/`dec()` accumulate, so they
 * reject any non-finite amount.
 *
 * @api
 */
final readonly class PrometheusGauge implements GaugeInterface
{
    /**
     * @param list<string> $labelNames
     */
    public function __construct(
        private Gauge $gauge,
        private array $labelNames,
    ) {}

    #[\Override]
    public function set(float $value, LabelSet $labels = new LabelSet()): void
    {
        Amount::assertNotNan($value);

        $this->gauge->set($value, Labels::order($labels, $this->labelNames));
    }

    #[\Override]
    public function inc(float $amount = 1.0, LabelSet $labels = new LabelSet()): void
    {
        Amount::assertFinite($amount);

        $this->gauge->incBy($amount, Labels::order($labels, $this->labelNames));
    }

    #[\Override]
    public function dec(float $amount = 1.0, LabelSet $labels = new LabelSet()): void
    {
        Amount::assertFinite($amount);

        $this->gauge->decBy($amount, Labels::order($labels, $this->labelNames));
    }
}
