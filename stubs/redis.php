<?php

declare(strict_types=1);

/** Psalm stub for the optional ext-redis dependency. */
class RedisException extends Exception {}

final class Redis
{
    public function __construct() {}

    public function getLastError(): ?string {}

    public function clearLastError(): bool {}

    public function evalSha(string $sha, array $args, int $numKeys): mixed {}

    /**
     * @param mixed[] $args
     */
    public function eval(string $script, array $args, int $numKeys): mixed {}
}
