<?php

declare(strict_types=1);

/** Psalm stub for the optional ext-redis dependency. */
final class Redis
{
    public function __construct() {}

    public function getLastError(): string|null {}

    public function clearLastError(): bool {}

    public function evalSha(string $sha, array $args, int $numKeys): mixed {}
}
