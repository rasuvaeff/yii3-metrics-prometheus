<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Internal;

use Prometheus\Storage\RedisClients\RedisClient;

/**
 * Decorates a promphp Redis client with optimistic EVALSHA writes.
 *
 * EVALSHA is tried first with the locally computed SHA-1 of the script; when
 * Redis replies NOSCRIPT (cold script cache: first write, Redis restart,
 * SCRIPT FLUSH), the write is retried as plain EVAL, which itself repopulates
 * the server-side script cache. There is no SCRIPT LOAD round trip and no
 * per-instance state, so the payload saving applies from the first write even
 * where the client is rebuilt on every request (php-fpm), and the mode keeps
 * working where the SCRIPT command is disabled by ACL.
 *
 * Connection handling and every non-eval command are delegated to the inner
 * promphp client, so option semantics cannot drift from the library.
 *
 * @internal
 */
abstract class AbstractEvalShaClient implements RedisClient
{
    public function __construct(private readonly RedisClient $client) {}

    /**
     * @param mixed[] $args
     */
    #[\Override]
    public function eval(string $script, array $args = [], int $num_keys = 0): void
    {
        if ($this->evalSha(sha1($script), $args, $num_keys)) {
            return;
        }

        $this->client->eval($script, $args, $num_keys);
    }

    /**
     * Executes EVALSHA; false means Redis replied NOSCRIPT. Any other failure throws.
     *
     * @param mixed[] $args
     */
    abstract protected function evalSha(string $sha, array $args, int $num_keys): bool;

    /**
     * Redis replies "NOSCRIPT No matching script. Please use EVAL." when its
     * script cache does not hold the SHA.
     */
    public static function isNoScript(string $message): bool
    {
        return str_starts_with(strtoupper($message), 'NOSCRIPT');
    }

    #[\Override]
    public function getPrefix(): ?string
    {
        return $this->client->getPrefix();
    }

    #[\Override]
    public function set(string $key, mixed $value, mixed $options = null): bool
    {
        return $this->client->set($key, $value, $options);
    }

    #[\Override]
    public function setNx(string $key, mixed $value): void
    {
        $this->client->setNx($key, $value);
    }

    #[\Override]
    public function sMembers(string $key): array
    {
        return $this->client->sMembers($key);
    }

    #[\Override]
    public function hGetAll(string $key): array|false
    {
        return $this->client->hGetAll($key);
    }

    #[\Override]
    public function keys(string $pattern): array
    {
        return $this->client->keys($pattern);
    }

    #[\Override]
    public function get(string $key): string|false
    {
        return $this->client->get($key);
    }

    #[\Override]
    public function del(array|string $key, string ...$other_keys): void
    {
        $this->client->del($key, ...$other_keys);
    }

    #[\Override]
    public function ensureOpenConnection(): void
    {
        $this->client->ensureOpenConnection();
    }
}
