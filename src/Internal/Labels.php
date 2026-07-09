<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Internal;

use Rasuvaeff\Yii3Metrics\LabelSet;

/**
 * Orders a {@see LabelSet}'s values by the metric's declared label names —
 * promphp stores names in registration order and expects values positionally in
 * that order. Missing labels become an empty string.
 *
 * @internal
 */
final class Labels
{
    private function __construct() {}

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    public static function order(LabelSet $labels, array $names): array
    {
        $values = [];

        foreach ($names as $name) {
            $values[] = $labels->labels[$name] ?? '';
        }

        return $values;
    }
}
