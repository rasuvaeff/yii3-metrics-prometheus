<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus;

use Prometheus\Storage\Adapter;
use Prometheus\Storage\APC;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\PDO as PdoAdapter;
use Prometheus\Storage\Redis;
use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;

/**
 * Builds a promphp storage adapter. In php-fpm each worker has its own process,
 * so `/metrics` is only correct with a SHARED adapter (`apcu`/`redis`/`pdo`) —
 * the `in_memory` default is per-process and suits CLI/RoadRunner/tests only.
 *
 * The `pdo` adapter needs a `dsn` option (plus optional `username`, `password`,
 * `prefix`); promphp supports MySQL, PostgreSQL, and SQLite.
 *
 * @api
 */
final readonly class StorageFactory
{
    public const string IN_MEMORY = 'in_memory';
    public const string APCU = 'apcu';
    public const string REDIS = 'redis';
    public const string PDO = 'pdo';

    /**
     * @param array<string, mixed> $options adapter-specific options (e.g. Redis host/port, PDO dsn)
     */
    public function create(string $adapter = self::IN_MEMORY, array $options = []): Adapter
    {
        return match ($adapter) {
            self::IN_MEMORY => new InMemory(),
            self::APCU, 'apc' => new APC(),
            self::REDIS => new Redis($options),
            self::PDO => $this->pdo($options),
            default => throw new InvalidArgumentException(\sprintf('Unknown storage adapter "%s"', $adapter)),
        };
    }

    /**
     * @param array<string, mixed> $options
     */
    private function pdo(array $options): PdoAdapter
    {
        $dsn = $options['dsn'] ?? null;

        if (!\is_string($dsn) || $dsn === '') {
            throw new InvalidArgumentException('PDO storage requires a non-empty "dsn" option');
        }

        $connection = new \PDO(
            $dsn,
            isset($options['username']) ? (string) $options['username'] : null,
            isset($options['password']) ? (string) $options['password'] : null,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
        );

        return new PdoAdapter(
            $connection,
            isset($options['prefix']) ? (string) $options['prefix'] : 'prometheus_',
        );
    }
}
