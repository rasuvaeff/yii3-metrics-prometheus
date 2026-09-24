<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Tests;

use Prometheus\Storage\Predis;
use Prometheus\Storage\Redis;
use Rasuvaeff\Yii3MetricsPrometheus\Internal\PhpRedisEvalShaClient;
use Rasuvaeff\Yii3MetricsPrometheus\Internal\PredisEvalShaClient;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

/**
 * The EVALSHA clients merge their own copies of promphp's connection defaults
 * before handing the options to promphp's own clients (promphp exposes no
 * public defaults API). This pins the copies to the vendor values: a promphp
 * upgrade changing them must fail here instead of drifting silently.
 */
#[Test]
#[Covers(PhpRedisEvalShaClient::class)]
#[Covers(PredisEvalShaClient::class)]
final class EvalShaPromphpDefaultsTest
{
    public function redisOptionsMirrorPromphpDefaults(): void
    {
        $vendor = $this->staticProperty(Redis::class, 'defaultOptions');
        $ours = $this->constant(PhpRedisEvalShaClient::class, 'DEFAULT_OPTIONS');

        Assert::same($ours, $vendor);
    }

    public function predisParametersMirrorPromphpDefaults(): void
    {
        $vendor = $this->staticProperty(Predis::class, 'defaultParameters');
        $ours = $this->constant(PredisEvalShaClient::class, 'DEFAULT_PARAMETERS');

        Assert::same($ours, $vendor);
    }

    public function predisOptionsMirrorPromphpDefaults(): void
    {
        $vendor = $this->staticProperty(Predis::class, 'defaultOptions');
        $ours = $this->constant(PredisEvalShaClient::class, 'DEFAULT_OPTIONS');

        Assert::same($ours, $vendor);
    }

    /** @return array<string, mixed> */
    private function staticProperty(string $class, string $property): array
    {
        /** @var array<string, mixed> $value */
        $value = (new \ReflectionProperty($class, $property))->getValue();

        \ksort($value);

        return $value;
    }

    /** @return array<string, mixed> */
    private function constant(string $class, string $name): array
    {
        /** @var array<string, mixed> $value */
        $value = (new \ReflectionClass($class))->getConstant($name);

        \ksort($value);

        return $value;
    }
}
