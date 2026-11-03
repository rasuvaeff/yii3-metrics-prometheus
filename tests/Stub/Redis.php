<?php

declare(strict_types=1);

namespace {
    // Minimal stand-in for ext-redis, loaded by Tests\RedisStub only when the
    // extension is absent. It carries just what the code under test touches
    // when it constructs the client; tests script behaviour with FakeRedis.
    if (!class_exists('Redis', autoload: false)) {
        /**
         * @internal
         */
        class RedisException extends RuntimeException {}

        /**
         * @internal
         */
        class Redis
        {
            public const int OPT_PREFIX = 2;

            public function getOption(int $option): mixed
            {
                return false;
            }
        }
    }
}
