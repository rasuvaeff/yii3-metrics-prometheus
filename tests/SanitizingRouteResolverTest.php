<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Rasuvaeff\Yii3MetricsPrometheus\SanitizingRouteResolver;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(SanitizingRouteResolver::class)]
final class SanitizingRouteResolverTest
{
    #[DataProvider('pathProvider')]
    public function collapsesIdLikeSegments(string $path, string $expected): void
    {
        $request = (new Psr17Factory())->createServerRequest('GET', 'https://example.test' . $path);

        Assert::same((new SanitizingRouteResolver())->resolve($request), $expected);
    }

    public static function pathProvider(): iterable
    {
        yield 'numeric id' => ['/users/123', '/users/:id'];
        yield 'nested ids' => ['/users/123/orders/456', '/users/:id/orders/:id'];
        yield 'uuid' => ['/items/550e8400-e29b-41d4-a716-446655440000', '/items/:uuid'];
        yield 'static path untouched' => ['/health/live', '/health/live'];
        yield 'root' => ['/', '/'];
    }
}
