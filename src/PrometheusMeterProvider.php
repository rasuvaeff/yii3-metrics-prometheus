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
 * `$namespace` prefixes every metric name in the exposition
 * (`<namespace>_<name>`) — promphp's standard namespacing.
 *
 * @api
 */
final class PrometheusMeterProvider implements MeterProviderInterface
{
    /** @var array<string, PrometheusMeter> keyed by instrumentation scope (`''` for none) */
    private array $meters = [];

    public function __construct(
        private readonly CollectorRegistry $registry,
        private readonly string $namespace = '',
    ) {}

    #[\Override]
    public function getMeter(?string $name = null): MeterInterface
    {
        // Memoized per scope; the meters are thin wrappers, the accumulating
        // state lives in the shared registry — two scopes still record into
        // the same series, per the core `(kind, name)` contract.
        return $this->meters[$name ?? ''] ??= new PrometheusMeter($this->registry, $this->namespace);
    }

    public function registry(): CollectorRegistry
    {
        return $this->registry;
    }
}
