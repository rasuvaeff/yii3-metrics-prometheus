<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Internal;

use Prometheus\Storage\RedisClients\PHPRedis;
use Prometheus\Storage\RedisClients\RedisClientException;

/**
 * phpredis reports a NOSCRIPT reply from evalSha() as false + getLastError()
 * instead of an exception; every other error reply — and any connection
 * failure — from evalSha()/eval() arrives as a raw \RedisException.
 * EVALSHA failure detection therefore reads getLastError() for the NOSCRIPT
 * case (it falls back to EVAL) and wraps the exception for real failures, so
 * fail-open consumers can see them. The fallback EVAL runs in the decorator
 * under the same check: failures of both paths surface as
 * RedisClientException (a raw \RedisException is wrapped, a reply that only
 * lands in getLastError() is asserted).
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

        try {
            $this->redis->evalSha($sha, $args, $num_keys);
        } catch (\RedisException $exception) {
            throw new RedisClientException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return self::classifyLastError($this->redis->getLastError());
    }

    /**
     * @param mixed[] $args
     */
    #[\Override]
    protected function evalFallback(string $script, array $args, int $num_keys): void
    {
        $this->redis->clearLastError();

        try {
            $this->redis->eval($script, $args, $num_keys);
        } catch (\RedisException $exception) {
            throw new RedisClientException($exception->getMessage(), $exception->getCode(), $exception);
        }

        self::assertNoLastError($this->redis->getLastError());
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

    /**
     * Throws on any phpredis error reply recorded for the fallback EVAL.
     * Unlike EVALSHA, plain EVAL cannot be answered with NOSCRIPT — the script
     * body travels with the command — so any recorded error is a real failure.
     */
    public static function assertNoLastError(?string $error): void
    {
        if ($error !== null && $error !== '') {
            throw new RedisClientException($error);
        }
    }
}
