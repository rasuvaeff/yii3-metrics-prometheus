<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus;

use Prometheus\Storage\AbstractRedis;
use Prometheus\Storage\RedisClients\RedisClient;
use Rasuvaeff\Yii3MetricsPrometheus\Internal\PhpRedisEvalShaClient;
use Rasuvaeff\Yii3MetricsPrometheus\Internal\PredisEvalShaClient;

/**
 * Promphp Redis storage with optimistic EVALSHA writes.
 *
 * It keeps promphp's Redis key and hash schema unchanged: writes address the
 * Lua scripts by SHA-1 and fall back to plain EVAL when Redis replies NOSCRIPT
 * (cold script cache, restart, SCRIPT FLUSH). Connection handling and every
 * non-eval command are delegated to promphp's own Redis clients, so option
 * semantics stay the library's.
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
     * @param array<string, mixed> $options Predis client options; defaults mirror promphp's Predis adapter
     */
    public static function predis(array $parameters, string $prefix, array $options = []): self
    {
        self::setPrefix($prefix);

        return new self(new PredisEvalShaClient($parameters, $options));
    }
}
