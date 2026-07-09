<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus;

use Prometheus\Storage\Adapter;
use Prometheus\Storage\APC;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Redis;

/**
 * Builds a promphp storage adapter. In php-fpm each worker has its own process,
 * so `/metrics` is only correct with a SHARED adapter (`apcu`/`redis`) — the
 * `in_memory` default is per-process and suits CLI/RoadRunner/tests only.
 *
 * @api
 */
final readonly class StorageFactory
{
    public const string IN_MEMORY = 'in_memory';
    public const string APCU = 'apcu';
    public const string REDIS = 'redis';

    /**
     * @param array<string, mixed> $options adapter-specific options (e.g. Redis host/port)
     */
    public function create(string $adapter = self::IN_MEMORY, array $options = []): Adapter
    {
        return match ($adapter) {
            self::APCU, 'apc' => new APC(),
            self::REDIS => new Redis($options),
            default => new InMemory(),
        };
    }
}
