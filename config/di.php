<?php

declare(strict_types=1);

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Adapter;
use Rasuvaeff\Yii3Metrics\MeterProviderInterface;
use Rasuvaeff\Yii3MetricsPrometheus\PrometheusMeterProvider;
use Rasuvaeff\Yii3MetricsPrometheus\StorageFactory;

/** @var array $params */

// The backend owns exactly ONE core key: MeterProviderInterface. It must NOT bind
// RouteResolverInterface — the core already binds PathRouteResolver, and a second
// vendor binding is a `yiisoft/config` "Duplicate key". Apps that want sanitizing
// rebind RouteResolverInterface => SanitizingRouteResolver at the app layer.
return [
    Adapter::class => static function (StorageFactory $factory) use ($params): Adapter {
        $config = $params['rasuvaeff/yii3-metrics-prometheus'];

        return $factory->create((string) $config['storage'], (array) $config['storage_options']);
    },

    // registerDefaultMetrics=false — keep php_info and friends out of the exposition.
    CollectorRegistry::class => static fn (Adapter $adapter): CollectorRegistry => new CollectorRegistry($adapter, false),

    MeterProviderInterface::class => static fn (CollectorRegistry $registry): MeterProviderInterface => new PrometheusMeterProvider($registry),
];
