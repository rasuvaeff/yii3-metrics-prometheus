<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;

/**
 * Renders a registry as Prometheus text exposition (v0.0.4).
 *
 * @api
 */
final readonly class PrometheusRenderer
{
    public const string CONTENT_TYPE = RenderTextFormat::MIME_TYPE;

    public function render(CollectorRegistry $registry): string
    {
        return (new RenderTextFormat())->render($registry->getMetricFamilySamples());
    }
}
