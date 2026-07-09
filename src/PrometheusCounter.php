<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus;

use Prometheus\Counter;
use Rasuvaeff\Yii3Metrics\CounterInterface;
use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;
use Rasuvaeff\Yii3Metrics\LabelSet;
use Rasuvaeff\Yii3MetricsPrometheus\Internal\Labels;

/**
 * Adapts a promphp counter to the core {@see CounterInterface}. Like any
 * recording counter it rejects a negative increment.
 *
 * @api
 */
final readonly class PrometheusCounter implements CounterInterface
{
    /**
     * @param list<string> $labelNames
     */
    public function __construct(
        private Counter $counter,
        private array $labelNames,
    ) {}

    #[\Override]
    public function inc(float $amount = 1.0, LabelSet $labels = new LabelSet()): void
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Counter cannot be decremented; use a gauge');
        }

        $this->counter->incBy($amount, Labels::order($labels, $this->labelNames));
    }
}
