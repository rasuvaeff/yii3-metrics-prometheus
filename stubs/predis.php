<?php

declare(strict_types=1);

namespace Predis;

/** Psalm stub for Predis' dynamic command methods. */
final class Client
{
    public function __construct(array $parameters = [], array $options = []) {}

    public function set(mixed ...$arguments): \Stringable|string {}
    public function setnx(mixed ...$arguments): mixed {}
    /** @return list<string> */
    public function smembers(mixed ...$arguments): array {}
    /** @return array<string, string>|false */
    public function hgetall(mixed ...$arguments): array|false {}
    /** @return list<string> */
    public function keys(mixed ...$arguments): array {}
    public function get(mixed ...$arguments): string|null {}
    public function del(mixed ...$arguments): mixed {}
    public function script(mixed ...$arguments): mixed {}
    public function evalsha(mixed ...$arguments): mixed {}
    public function eval(mixed ...$arguments): mixed {}
    public function isConnected(): bool {}
    public function connect(): void {}
}
