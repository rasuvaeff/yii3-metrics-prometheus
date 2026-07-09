<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus;

use Prometheus\CollectorRegistry;
use Rasuvaeff\Yii3Metrics\MeterInterface;
use Rasuvaeff\Yii3Metrics\MeterProviderInterface;

/**
 * Backend {@see MeterProviderInterface} over a promphp {@see CollectorRegistry}.
 * This is the single binding that owns the swappable provider key in the app.
 *
 * @api
 */
final class PrometheusMeterProvider implements MeterProviderInterface
{
    private ?PrometheusMeter $meter = null;

    public function __construct(
        private readonly CollectorRegistry $registry,
    ) {}

    #[\Override]
    public function getMeter(?string $name = null): MeterInterface
    {
        return $this->meter ??= new PrometheusMeter($this->registry);
    }

    public function registry(): CollectorRegistry
    {
        return $this->registry;
    }
}
