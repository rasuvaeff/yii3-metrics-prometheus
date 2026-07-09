<?php

declare(strict_types=1);

use Rasuvaeff\Yii3MetricsPrometheus\MetricsEndpoint;

// The `/metrics` endpoint is web-only. Route the application's `/metrics` path to
// it. It needs a PSR-17 ResponseFactoryInterface from the app container.
return [
    MetricsEndpoint::class => MetricsEndpoint::class,
];
