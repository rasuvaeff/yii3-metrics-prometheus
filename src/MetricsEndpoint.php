<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus;

use Prometheus\CollectorRegistry;
use Prometheus\Exception\StorageException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

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
        private ?LoggerInterface $logger = null,
    ) {}

    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $body = $this->renderer->render($this->registry);
        } catch (StorageException $exception) {
            $this->logger?->warning('Metrics storage unavailable', [
                'exception' => $exception->getMessage(),
            ]);
            $response = $this->responseFactory
                ->createResponse(503)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8');
            $response->getBody()->write('metrics storage unavailable');

            return $response;
        }

        $response = $this->responseFactory
            ->createResponse(200)
            ->withHeader('Content-Type', PrometheusRenderer::CONTENT_TYPE);

        $response->getBody()->write($body);

        return $response;
    }
}
