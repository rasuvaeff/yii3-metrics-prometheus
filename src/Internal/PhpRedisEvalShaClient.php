<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Internal;

use Prometheus\Exception\StorageException;
use Prometheus\Storage\RedisClients\RedisClientException;

/** @internal */
final class PhpRedisEvalShaClient extends AbstractEvalShaClient
{
    private bool $connectionInitialized = false;

    private readonly \Redis $redis;

    /** @param array<string, mixed> $options */
    public function __construct(private readonly array $options)
    {
        $this->redis = new \Redis();
    }

    #[\Override]
    public function getPrefix(): ?string
    {
        /** @var string|null $prefix */
        $prefix = $this->redis->getOption(\Redis::OPT_PREFIX);

        return is_string($prefix) && $prefix !== '' ? $prefix : null;
    }

    #[\Override]
    public function set(string $key, mixed $value, mixed $options = null): bool
    {
        return $this->redis->set($key, $value, $options);
    }

    #[\Override]
    public function setNx(string $key, mixed $value): void
    {
        $this->redis->setNx($key, $value); // @phpstan-ignore-line
    }

    #[\Override]
    public function sMembers(string $key): array
    {
        /** @var list<string> $members */
        $members = $this->redis->sMembers($key);

        return $members;
    }

    #[\Override]
    public function hGetAll(string $key): array|false
    {
        /** @var array<string, string>|false $values */
        $values = $this->redis->hGetAll($key);

        return $values;
    }

    #[\Override]
    public function keys(string $pattern): array
    {
        /** @var list<string> $keys */
        $keys = $this->redis->keys($pattern);

        return $keys;
    }

    #[\Override]
    public function get(string $key): string|false
    {
        return $this->redis->get($key);
    }

    #[\Override]
    public function del(array|string $key, string ...$other_keys): void
    {
        try {
            $this->redis->del($key, ...$other_keys);
        } catch (\RedisException $exception) {
            throw new RedisClientException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    #[\Override]
    public function ensureOpenConnection(): void
    {
        if ($this->connectionInitialized) {
            return;
        }

        try {
            $persistent = (bool) ($this->options['persistent_connections'] ?? false);
            $connected = $persistent
                ? $this->redis->pconnect(
                    (string) ($this->options['host'] ?? '127.0.0.1'),
                    (int) ($this->options['port'] ?? 6379),
                    (float) ($this->options['timeout'] ?? 0.1),
                )
                : $this->redis->connect(
                    (string) ($this->options['host'] ?? '127.0.0.1'),
                    (int) ($this->options['port'] ?? 6379),
                    (float) ($this->options['timeout'] ?? 0.1),
                );

            if (!$connected) {
                throw new StorageException("Can't connect to Redis server. {$this->redis->getLastError()}");
            }

            $authParams = [];
            if (isset($this->options['user']) && $this->options['user'] !== '') {
                $authParams[] = (string) $this->options['user'];
            }
            if (isset($this->options['password'])) {
                $authParams[] = (string) $this->options['password'];
            }
            if ($authParams !== []) {
                $this->redis->auth($authParams);
            }

            if (isset($this->options['database'])) {
                $this->redis->select((int) $this->options['database']);
            }

            $this->redis->setOption(\Redis::OPT_READ_TIMEOUT, $this->options['read_timeout'] ?? 10);
            $this->connectionInitialized = true;
        } catch (\RedisException $exception) {
            throw new StorageException("Can't connect to Redis server. {$exception->getMessage()}", $exception->getCode(), $exception);
        }
    }

    #[\Override]
    protected function scriptLoad(string $script): void
    {
        $this->redis->script('load', $script);
    }

    #[\Override]
    protected function evalSha(string $sha, array $args, int $num_keys): void
    {
        $this->redis->evalSha($sha, $args, $num_keys);
    }

    #[\Override]
    protected function rawEval(string $script, array $args, int $num_keys): void
    {
        $this->redis->eval($script, $args, $num_keys);
    }
}
