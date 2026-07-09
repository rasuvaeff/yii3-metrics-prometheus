<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus;

use Psr\Http\Message\ServerRequestInterface;
use Rasuvaeff\Yii3Metrics\RouteResolverInterface;

/**
 * Core {@see RouteResolverInterface} that collapses id-like path segments
 * (numeric ids, UUIDs) into `:id` / `:uuid`, keeping the RED `route` label
 * low-cardinality.
 *
 * Opt-in: the core binds the default `PathRouteResolver`, so an application that
 * wants sanitizing rebinds `RouteResolverInterface => SanitizingRouteResolver` in
 * its own config (an app-layer override, not a second vendor binding).
 *
 * @api
 */
final readonly class SanitizingRouteResolver implements RouteResolverInterface
{
    private const string ID_PATTERN = '/^\d+$/';
    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    #[\Override]
    public function resolve(ServerRequestInterface $request): string
    {
        $segments = explode('/', $request->getUri()->getPath());

        foreach ($segments as $index => $segment) {
            if ($segment === '') {
                continue;
            }

            if (preg_match(self::ID_PATTERN, $segment) === 1) {
                $segments[$index] = ':id';
            } elseif (preg_match(self::UUID_PATTERN, $segment) === 1) {
                $segments[$index] = ':uuid';
            }
        }

        return implode('/', $segments);
    }
}
