<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus;

use Prometheus\CollectorRegistry;
use Rasuvaeff\Yii3Metrics\Internal\RegistrationGuard;
use Rasuvaeff\Yii3Metrics\MeterInterface;
use Rasuvaeff\Yii3Metrics\MeterProviderInterface;

/**
 * Backend {@see MeterProviderInterface} over a promphp {@see CollectorRegistry}.
 * This is the single binding that owns the swappable provider key in the app.
 *
 * `$namespace` prefixes every metric name in the exposition
 * (`<namespace>_<name>`) — promphp's standard namespacing.
 *
 * `$strictNaming` enables the core's registration checks (suffix conventions,
 * conflicting re-registration, series collisions). One guard is shared by every
 * scoped meter, since metric state is global per `(kind, name)`. It is
 * per-process: two workers registering different definitions are not compared.
 *
 * @api
 */
final class PrometheusMeterProvider implements MeterProviderInterface
{
    /** @var array<string, PrometheusMeter> keyed by instrumentation scope (`''` for none) */
    private array $meters = [];

    private readonly ?RegistrationGuard $guard;

    public function __construct(
        private readonly CollectorRegistry $registry,
        private readonly string $namespace = '',
        bool $strictNaming = false,
    ) {
        $this->guard = $strictNaming ? new RegistrationGuard() : null;
    }

    #[\Override]
    public function getMeter(?string $name = null): MeterInterface
    {
        // Memoized per scope; the meters are thin wrappers, the accumulating
        // state lives in the shared registry — two scopes still record into
        // the same series, per the core `(kind, name)` contract.
        return $this->meters[$name ?? ''] ??= new PrometheusMeter($this->registry, $this->namespace, $this->guard);
    }

    public function registry(): CollectorRegistry
    {
        return $this->registry;
    }
}
