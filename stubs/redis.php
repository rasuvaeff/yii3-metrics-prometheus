<?php

declare(strict_types=1);

/** Psalm stub for the optional ext-redis dependency. */
final class Redis
{
    public const int OPT_PREFIX = 2;
    public const int OPT_READ_TIMEOUT = 3;

    public function __construct() {}
    public function getOption(int $option): string|null {}
    public function setOption(int $option, mixed $value): bool {}
    public function isConnected(): bool {}
    public function connect(string $host, int $port, float $timeout): bool {}
    public function pconnect(string $host, int $port, float $timeout): bool {}
    public function getLastError(): string {}
    public function auth(array $credentials): bool {}
    public function select(int $database): bool {}
    public function set(string $key, mixed $value, mixed $options = null): bool {}
    public function setNx(string $key, mixed $value): bool {}
    /** @return list<string> */
    public function sMembers(string $key): array {}
    /** @return array<string, string>|false */
    public function hGetAll(string $key): array|false {}
    /** @return list<string> */
    public function keys(string $pattern): array {}
    public function get(string $key): string|false {}
    public function del(array|string $key, string ...$otherKeys): int {}
    public function script(string $command, string $script): string {}
    public function evalSha(string $sha, array $args, int $numKeys): mixed {}
    public function eval(string $script, array $args, int $numKeys): mixed {}
}

final class RedisException extends Exception {}
