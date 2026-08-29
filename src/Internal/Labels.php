<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Internal;

use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;
use Rasuvaeff\Yii3Metrics\LabelSet;

/**
 * Orders a {@see LabelSet}'s values by the metric's declared label names —
 * promphp stores names in registration order and expects values positionally in
 * that order. A DECLARED label missing from the set throws, and so does an
 * UNDECLARED one: both are programmer errors (a forgotten label, a typo'd
 * label name), and recording either silently — the missing one as `""`, the
 * undeclared one dropped — corrupts the series exactly the same way.
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
            if (!\array_key_exists($name, $labels->labels)) {
                throw new InvalidArgumentException(\sprintf(
                    'Missing label "%s" for this metric; passed: "%s"',
                    $name,
                    implode('", "', array_keys($labels->labels)),
                ));
            }

            $values[] = $labels->labels[$name];
        }

        if (\count($values) !== \count($labels->labels)) {
            $undeclared = array_diff(array_keys($labels->labels), $names);

            throw new InvalidArgumentException(\sprintf(
                'Undeclared label(s) "%s" for this metric; declared: "%s"',
                implode('", "', $undeclared),
                implode('", "', $names),
            ));
        }

        return $values;
    }
}
