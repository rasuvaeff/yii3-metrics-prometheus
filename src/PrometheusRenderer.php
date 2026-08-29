<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;

/**
 * Renders a registry as Prometheus text exposition (v0.0.4).
 *
 * Silent mode: a sample whose labels no longer match its metric (a Redis
 * storage can hold such rows) is rendered as a comment instead of throwing —
 * one broken entry used to turn the whole `/metrics` scrape into a 500 until
 * the storage was flushed, losing all monitoring over a single bad sample.
 *
 * @api
 */
final readonly class PrometheusRenderer
{
    public const string CONTENT_TYPE = RenderTextFormat::MIME_TYPE;

    public function render(CollectorRegistry $registry): string
    {
        return (new RenderTextFormat())->render($registry->getMetricFamilySamples(), silent: true);
    }
}
