<?php

declare(strict_types=1);

return [
    'rasuvaeff/yii3-metrics-prometheus' => [
        // `in_memory` (per-process), `apcu`, `redis`, or `pdo`. php-fpm needs a
        // shared adapter (apcu/redis/pdo) — see StorageFactory.
        'storage' => getenv('PROMETHEUS_STORAGE') ?: 'in_memory',
        'storage_options' => [],
    ],
];
