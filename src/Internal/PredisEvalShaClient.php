<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Internal;

use Predis\Client;
use Predis\Connection\ConnectionException;
use Prometheus\Exception\StorageException;

/** @internal */
final class PredisEvalShaClient extends AbstractEvalShaClient
{
    /** @var array<string, mixed> */
    private const array DEFAULT_PARAMETERS = [
        'scheme' => 'tcp',
        'host' => '127.0.0.1',
        'port' => 6379,
        'timeout' => 0.1,
        'read_write_timeout' => 10,
        'persistent' => false,
        'password' => null,
        'username' => null,
    ];

    /** @var array<string, mixed> */
    private const array DEFAULT_OPTIONS = [
        'prefix' => '',
        'throw_errors' => true,
    ];

    private readonly Client $client;

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $options
     */
    public function __construct(array $parameters, array $options)
    {
        $this->client = new Client(
            array_merge(self::DEFAULT_PARAMETERS, $parameters),
            array_merge(self::DEFAULT_OPTIONS, $options),
        );
    }

    #[\Override]
    public function getPrefix(): ?string
    {
        // StorageFactory consumes the application prefix before constructing
        // this client; AbstractRedis owns the promphp metric prefix.
        return null;
    }

    #[\Override]
    public function set(string $key, mixed $value, mixed $options = null): bool
    {
        $result = $this->client->set($key, $value, ...$this->flattenFlags($options));

        return (string) $result === 'OK';
    }

    #[\Override]
    public function setNx(string $key, mixed $value): void
    {
        $this->client->setnx($key, $value);
    }

    #[\Override]
    public function sMembers(string $key): array
    {
        /** @var array<int, string> $members */
        $members = $this->client->smembers($key);

        return $members;
    }

    #[\Override]
    public function hGetAll(string $key): array|false
    {
        /** @var array<string, string>|false $values */
        $values = $this->client->hgetall($key);

        return $values;
    }

    #[\Override]
    public function keys(string $pattern): array
    {
        /** @var array<int, string> $keys */
        $keys = $this->client->keys($pattern);

        return $keys;
    }

    #[\Override]
    public function get(string $key): string|false
    {
        $value = $this->client->get($key);

        return $value ?? false;
    }

    #[\Override]
    public function del(array|string $key, string ...$other_keys): void
    {
        $this->client->del($key, ...$other_keys);
    }

    #[\Override]
    public function ensureOpenConnection(): void
    {
        if ($this->client->isConnected()) {
            return;
        }

        try {
            $this->client->connect();
        } catch (ConnectionException $exception) {
            throw new StorageException('Cannot establish Redis Connection:' . $exception->getMessage(), 0, $exception);
        }
    }

    #[\Override]
    protected function scriptLoad(string $script): void
    {
        $this->client->script('load', $script);
    }

    #[\Override]
    protected function evalSha(string $sha, array $args, int $num_keys): void
    {
        $this->client->evalsha($sha, $num_keys, ...$this->stringArguments($args));
    }

    #[\Override]
    protected function rawEval(string $script, array $args, int $num_keys): void
    {
        $this->client->eval($script, $num_keys, ...$this->stringArguments($args));
    }

    /**
     * @return list<string|int|float>
     */
    private function flattenFlags(mixed $flags): array
    {
        if (!is_array($flags)) {
            return [];
        }

        /** @var array<int|string, string|int|float> $typedFlags */
        $typedFlags = $flags;
        $result = [];
        foreach ($typedFlags as $key => $value) {
            if (is_int($key)) {
                $result[] = $value;
            } else {
                $result[] = $key;
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $args
     *
     * @return list<string>
     */
    private function stringArguments(array $args): array
    {
        /** @var list<string> $result */
        $result = array_map(
            static fn(mixed $value): string => (string) $value,
            array_values($args),
        );

        return $result;
    }
}
