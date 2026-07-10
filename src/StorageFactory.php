<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus;

use Prometheus\Storage\Adapter;
use Prometheus\Storage\APC;
use Prometheus\Storage\APCng;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\PDO as PdoAdapter;
use Prometheus\Storage\Predis;
use Prometheus\Storage\Redis;
use Rasuvaeff\Yii3Metrics\Exception\InvalidArgumentException;

/**
 * Builds a promphp storage adapter. In php-fpm each worker has its own process,
 * so `/metrics` is only correct with a SHARED adapter (`apcu`/`apcng`/`redis`/
 * `predis`/`pdo`) — the `in_memory` default is per-process and suits
 * CLI/RoadRunner/tests only; selecting it under php-fpm raises an
 * `E_USER_WARNING` so the misconfiguration is visible instead of silently
 * exposing one worker's counters.
 *
 * `apcng` is promphp's newer APCu adapter with much cheaper collection on large
 * registries — prefer it over `apcu` for new deployments. `predis` uses the
 * pure-PHP `predis/predis` client (no `ext-redis` needed); its options are the
 * `Predis\Client` connection parameters. The `pdo` adapter needs a `dsn` option
 * (plus optional `username`, `password`, `prefix`); promphp supports MySQL,
 * PostgreSQL, and SQLite.
 *
 * @api
 */
final readonly class StorageFactory
{
    public const string IN_MEMORY = 'in_memory';
    public const string APCU = 'apcu';
    public const string APCNG = 'apcng';
    public const string REDIS = 'redis';
    public const string PREDIS = 'predis';
    public const string PDO = 'pdo';

    /**
     * @param string $sapi injectable for tests only; the fpm warning keys off it
     */
    public function __construct(
        private string $sapi = \PHP_SAPI,
    ) {}

    /**
     * @param array<string, mixed> $options adapter-specific options (e.g. Redis host/port, Predis connection parameters, PDO dsn)
     */
    public function create(string $adapter = self::IN_MEMORY, array $options = []): Adapter
    {
        if ($adapter === self::IN_MEMORY && $this->sapi === 'fpm-fcgi') {
            trigger_error(
                'Prometheus "in_memory" storage under php-fpm is per-worker: /metrics will expose only the serving worker. Use a shared adapter (apcu/apcng/redis/predis/pdo)',
                E_USER_WARNING,
            );
        }

        return match ($adapter) {
            self::IN_MEMORY => new InMemory(),
            self::APCU, 'apc' => new APC(),
            self::APCNG => new APCng(),
            self::REDIS => new Redis($options),
            self::PREDIS => new Predis($options),
            self::PDO => $this->pdo($options),
            default => throw new InvalidArgumentException(\sprintf('Unknown storage adapter "%s"', $adapter)),
        };
    }

    /**
     * Normalized PDO settings: validated dsn plus username/password/prefix
     * with their defaults applied.
     *
     * @param array<string, mixed> $options
     *
     * @return array{dsn: string, username: ?string, password: ?string, prefix: string}
     *
     * @internal exposed for tests
     */
    public function pdoConfig(array $options): array
    {
        $dsn = $options['dsn'] ?? null;

        if (!\is_string($dsn) || $dsn === '') {
            throw new InvalidArgumentException('PDO storage requires a non-empty "dsn" option');
        }

        return [
            'dsn' => $dsn,
            'username' => isset($options['username']) ? (string) $options['username'] : null,
            'password' => isset($options['password']) ? (string) $options['password'] : null,
            'prefix' => isset($options['prefix']) ? (string) $options['prefix'] : 'prometheus_',
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function pdo(array $options): PdoAdapter
    {
        $config = $this->pdoConfig($options);

        $connection = new \PDO(
            dsn: $config['dsn'],
            username: $config['username'],
            password: $config['password'],
            options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
        );

        return new PdoAdapter(
            database: $connection,
            prefix: $config['prefix'],
        );
    }
}
