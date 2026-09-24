<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus;

use Prometheus\Storage\AbstractRedis;
use Prometheus\Storage\RedisClients\RedisClient;
use Rasuvaeff\Yii3MetricsPrometheus\Internal\PhpRedisEvalShaClient;
use Rasuvaeff\Yii3MetricsPrometheus\Internal\PredisEvalShaClient;

/**
 * Promphp Redis storage with per-client Lua script caching.
 *
 * It keeps promphp's Redis key and hash schema unchanged. The first execution
 * loads each script, subsequent writes use EVALSHA, and a NOSCRIPT response
 * falls back to EVAL for the current write. This reduces payload size while
 * preserving one storage operation per metric write.
 *
 * @internal
 */
final class EvalShaRedis extends AbstractRedis
{
    public function __construct(RedisClient $client)
    {
        $this->redis = $client;
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function redis(array $options, string $prefix): self
    {
        self::setPrefix($prefix);

        return new self(new PhpRedisEvalShaClient($options));
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $options
     */
    public static function predis(array $parameters, array $options, string $prefix): self
    {
        self::setPrefix($prefix);

        return new self(new PredisEvalShaClient($parameters, $options));
    }
}
