<?php

declare(strict_types=1);

namespace Predis;

/** Psalm stub for Predis' dynamic command methods. */
final class Client
{
    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $options
     */
    public function __construct(array $parameters = [], array $options = []) {}

    public function evalsha(mixed ...$arguments): mixed {}
}
