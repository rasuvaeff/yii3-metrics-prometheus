<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Tests;

/**
 * ext-redis is not installed in every environment that runs the unit suite.
 * When it is absent, a minimal `\Redis` class (tests/Stub/Redis.php) stands in
 * so the phpredis code paths can be constructed and driven through FakeRedis.
 *
 * @internal
 */
final class RedisStub
{
    public static function install(): void
    {
        if (!class_exists(\Redis::class, autoload: false)) {
            require_once __DIR__ . '/Stub/Redis.php';
        }
    }
}
