<?php

declare(strict_types=1);

namespace {
    // Test stand-in for ext-redis, loaded only when the extension is absent
    // (see Tests\RedisStub). It records calls and replays scripted replies.
    if (!class_exists('Redis', autoload: false)) {
        /**
         * @internal
         */
        final class RedisException extends RuntimeException {}

        /**
         * @internal
         */
        final class Redis
        {
            public const int OPT_PREFIX = 2;

            /** @var list<array{string, list<mixed>}> */
            public static array $calls = [];

            public static ?string $lastError = null;

            public static ?string $errorAfterEvalSha = null;

            public static ?string $errorAfterEval = null;

            public static ?RedisException $throwOnEvalSha = null;

            public static ?RedisException $throwOnEval = null;

            public static function reset(): void
            {
                self::$calls = [];
                self::$lastError = null;
                self::$errorAfterEvalSha = null;
                self::$errorAfterEval = null;
                self::$throwOnEvalSha = null;
                self::$throwOnEval = null;
            }

            public function clearLastError(): bool
            {
                self::$calls[] = ['clearLastError', []];
                self::$lastError = null;

                return true;
            }

            public function getLastError(): ?string
            {
                return self::$lastError;
            }

            /**
             * @param array<mixed> $args
             */
            public function evalSha(string $sha, array $args = [], int $numKeys = 0): mixed
            {
                self::$calls[] = ['evalSha', [$sha, $args, $numKeys]];

                if (self::$throwOnEvalSha instanceof \RedisException) {
                    throw self::$throwOnEvalSha;
                }

                self::$lastError = self::$errorAfterEvalSha;

                return false;
            }

            /**
             * @param array<mixed> $args
             */
            public function eval(string $script, array $args = [], int $numKeys = 0): mixed
            {
                self::$calls[] = ['eval', [$script, $args, $numKeys]];

                if (self::$throwOnEval instanceof \RedisException) {
                    throw self::$throwOnEval;
                }

                self::$lastError = self::$errorAfterEval;

                return false;
            }

            public function getOption(int $option): mixed
            {
                return false;
            }
        }
    }
}
