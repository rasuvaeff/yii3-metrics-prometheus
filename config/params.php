<?php

declare(strict_types=1);

return [
    'rasuvaeff/yii3-metrics-prometheus' => [
        // `in_memory` (per-process), `apcu`, `apcng`, `redis`, `predis`, or
        // `pdo`. php-fpm needs a shared adapter (apcu/apcng/redis/predis/pdo) —
        // see StorageFactory (in_memory under fpm triggers an E_USER_WARNING).
        'storage' => getenv('PROMETHEUS_STORAGE') ?: 'in_memory',
        // Redis/Predis prefix is applied before adapter creation. It is
        // process-global in promphp; use one prefix per application process.
        'storage_options' => [],
        // Optional metric-name prefix: `<namespace>_<name>` in the exposition.
        'namespace' => getenv('PROMETHEUS_NAMESPACE') ?: '',
    ],
];
