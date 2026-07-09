<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus;

use Prometheus\CollectorRegistry;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 handler for the `/metrics` endpoint: renders the registry as Prometheus
 * text exposition with the correct `Content-Type`.
 *
 * @api
 */
final readonly class MetricsEndpoint implements RequestHandlerInterface
{
    public function __construct(
        private CollectorRegistry $registry,
        private ResponseFactoryInterface $responseFactory,
        private PrometheusRenderer $renderer = new PrometheusRenderer(),
    ) {}

    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->responseFactory
            ->createResponse(200)
            ->withHeader('Content-Type', PrometheusRenderer::CONTENT_TYPE);

        $response->getBody()->write($this->renderer->render($this->registry));

        return $response;
    }
}
