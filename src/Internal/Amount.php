<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Internal;

use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;

/**
 * Rejects non-finite recorded amounts before they reach a promphp storage
 * adapter.
 *
 * The core in-memory instruments apply the identical guard; the backend cannot
 * borrow it (it lives behind the core's `@internal` Validation) but it MUST match
 * it, or the same code behaves differently per backend. promphp itself does not
 * guard: `NAN` fails every comparison, so it passes the bucket-ordering check and
 * lands in a shared APCu/Redis/PDO storage, where it survives the request and
 * poisons the series total (`NAN + x === NAN`) until the storage is flushed.
 *
 * @internal
 */
final class Amount
{
    private function __construct() {}

    public static function assertFinite(float $amount): void
    {
        if (!is_finite($amount)) {
            throw new InvalidArgumentException('Metric amount must be finite');
        }
    }

    /**
     * A gauge's absolute `set()` may carry `±INF` — promphp renders `+Inf` /
     * `-Inf` for it (`Sample::getValue()`). `NAN` has no such branch: it falls
     * through to `(string) $value`, which emits the token `NAN` (the exposition
     * format spells it `NaN`) and, since PHP 8.5, raises a warning while coercing
     * it — under `yiisoft/error-handler` that warning becomes an `ErrorException`
     * and the whole `/metrics` render 500s.
     */
    public static function assertNotNan(float $value): void
    {
        if (is_nan($value)) {
            throw new InvalidArgumentException('Metric value must not be NaN');
        }
    }
}
