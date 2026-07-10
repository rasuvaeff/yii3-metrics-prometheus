<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Internal;

use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;
use Rasuvaeff\Yii3Metrics\LabelSet;

/**
 * Orders a {@see LabelSet}'s values by the metric's declared label names —
 * promphp stores names in registration order and expects values positionally in
 * that order. Missing declared labels become an empty string; an UNDECLARED
 * label throws — it is a programmer error (typo'd label name), and recording it
 * silently under an empty value would corrupt the series.
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
        $undeclared = array_diff(array_keys($labels->labels), $names);

        if ($undeclared !== []) {
            throw new InvalidArgumentException(\sprintf(
                'Undeclared label(s) "%s" for this metric; declared: "%s"',
                implode('", "', $undeclared),
                implode('", "', $names),
            ));
        }

        $values = [];

        foreach ($names as $name) {
            $values[] = $labels->labels[$name] ?? '';
        }

        return $values;
    }
}
