<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Tests;

/**
 * ext-redis is not installed in the unit-test environment, and the mutation job
 * has no `redis` extension either. When it is absent, a stand-in `\Redis`
 * class (tests/Stub/Redis.php) lets the phpredis code paths run.
 *
 * @internal
 */
final class RedisStub
{
    /**
     * True when `\Redis` is the recording stand-in (the real extension is
     * absent); scripted-reply tests only make sense then.
     */
    public static function install(): bool
    {
        if (!class_exists(\Redis::class, autoload: false)) {
            require_once __DIR__ . '/Stub/Redis.php';
        }

        if (!method_exists(\Redis::class, 'reset')) {
            return false;
        }

        \Redis::reset();

        return true;
    }
}
