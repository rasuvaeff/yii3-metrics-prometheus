<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Internal;

use Prometheus\Storage\RedisClients\PHPRedis;
use Prometheus\Storage\RedisClients\RedisClientException;

/**
 * phpredis does not throw on Redis error replies: the command returns false
 * and the message lands in getLastError(). EVALSHA failure detection therefore
 * keys off getLastError() — a NOSCRIPT reply falls back to EVAL, any other
 * error is thrown so fail-open consumers can see it.
 *
 * @internal
 */
final class PhpRedisEvalShaClient extends AbstractEvalShaClient
{
    /**
     * Mirrors promphp's Redis adapter defaults; pinned to the vendor values by
     * EvalShaPromphpDefaultsTest.
     */
    private const array DEFAULT_OPTIONS = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'timeout' => 0.1,
        'read_timeout' => '10',
        'persistent_connections' => false,
        'password' => null,
        'user' => null,
    ];

    private readonly \Redis $redis;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options)
    {
        $this->redis = new \Redis();

        parent::__construct(new PHPRedis($this->redis, array_merge(self::DEFAULT_OPTIONS, $options)));
    }

    /**
     * @param mixed[] $args
     */
    #[\Override]
    protected function evalSha(string $sha, array $args, int $num_keys): bool
    {
        $this->redis->clearLastError();
        $this->redis->evalSha($sha, $args, $num_keys);

        return self::classifyLastError($this->redis->getLastError());
    }

    /**
     * Classifies the phpredis lastError after an EVALSHA call: null/'' means
     * the script ran, a NOSCRIPT prefix means the server-side script cache
     * missed, anything else is a real failure.
     */
    public static function classifyLastError(?string $error): bool
    {
        if ($error === null || $error === '') {
            return true;
        }

        if (self::isNoScript($error)) {
            return false;
        }

        throw new RedisClientException($error);
    }
}
