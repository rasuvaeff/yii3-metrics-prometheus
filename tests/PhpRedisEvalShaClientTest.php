<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Tests;

use Prometheus\Storage\RedisClients\PHPRedis;
use Prometheus\Storage\RedisClients\RedisClientException;
use Rasuvaeff\Yii3MetricsPrometheus\Internal\AbstractEvalShaClient;
use Rasuvaeff\Yii3MetricsPrometheus\Internal\PhpRedisEvalShaClient;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

/**
 * Runs against the recording `\Redis` stand-in; with the real extension loaded
 * there is nothing to script, so every test returns early.
 */
#[Test]
#[Covers(PhpRedisEvalShaClient::class)]
final class PhpRedisEvalShaClientTest
{
    public function sendsEvalShaOnceAfterClearingStaleErrors(): void
    {
        if (!RedisStub::install()) {
            return;
        }

        \Redis::$lastError = 'NOSCRIPT stale error from an earlier command';

        (new PhpRedisEvalShaClient([]))->eval('return 1', ['key', 'arg'], 1);

        Assert::same(\Redis::$calls, [
            ['clearLastError', []],
            ['evalSha', [sha1('return 1'), ['key', 'arg'], 1]],
        ]);
    }

    public function fallsBackToEvalOnNoscriptAfterClearingTheError(): void
    {
        if (!RedisStub::install()) {
            return;
        }

        \Redis::$errorAfterEvalSha = 'NOSCRIPT No matching script. Please use EVAL.';
        \Redis::$errorAfterEval = null;

        (new PhpRedisEvalShaClient([]))->eval('return 1', ['key'], 1);

        Assert::same(\Redis::$calls, [
            ['clearLastError', []],
            ['evalSha', [sha1('return 1'), ['key'], 1]],
            ['clearLastError', []],
            ['eval', ['return 1', ['key'], 1]],
        ]);
    }

    public function failsWhenEvalShaRepliesWithAnotherError(): void
    {
        if (!RedisStub::install()) {
            return;
        }

        \Redis::$errorAfterEvalSha = 'ERR wrong number of arguments';

        try {
            (new PhpRedisEvalShaClient([]))->eval('return 1');
            Assert::fail('expected a RedisClientException');
        } catch (RedisClientException $exception) {
            Assert::same($exception->getMessage(), 'ERR wrong number of arguments');
        }

        Assert::same(\count(\Redis::$calls), 2);
    }

    public function failsWhenTheFallbackEvalRepliesWithAnError(): void
    {
        if (!RedisStub::install()) {
            return;
        }

        \Redis::$errorAfterEvalSha = 'NOSCRIPT No matching script. Please use EVAL.';
        \Redis::$errorAfterEval = 'ERR Error compiling script';

        try {
            (new PhpRedisEvalShaClient([]))->eval('return 1');
            Assert::fail('expected a RedisClientException');
        } catch (RedisClientException $exception) {
            Assert::same($exception->getMessage(), 'ERR Error compiling script');
        }
    }

    public function wrapsConnectionFailuresOfEvalSha(): void
    {
        if (!RedisStub::install()) {
            return;
        }

        $failure = new \RedisException('connection lost', 7);
        \Redis::$throwOnEvalSha = $failure;

        try {
            (new PhpRedisEvalShaClient([]))->eval('return 1');
            Assert::fail('expected a RedisClientException');
        } catch (RedisClientException $exception) {
            Assert::same($exception->getMessage(), 'connection lost');
            Assert::same($exception->getCode(), 7);
            Assert::same($exception->getPrevious(), $failure);
        }
    }

    public function wrapsConnectionFailuresOfTheFallbackEval(): void
    {
        if (!RedisStub::install()) {
            return;
        }

        \Redis::$errorAfterEvalSha = 'NOSCRIPT No matching script. Please use EVAL.';
        $failure = new \RedisException('connection reset', 9);
        \Redis::$throwOnEval = $failure;

        try {
            (new PhpRedisEvalShaClient([]))->eval('return 1');
            Assert::fail('expected a RedisClientException');
        } catch (RedisClientException $exception) {
            Assert::same($exception->getMessage(), 'connection reset');
            Assert::same($exception->getCode(), 9);
            Assert::same($exception->getPrevious(), $failure);
        }
    }

    public function mergesOptionsOverPromphpDefaults(): void
    {
        if (!RedisStub::install()) {
            return;
        }

        Assert::same($this->innerOptions(new PhpRedisEvalShaClient([])), [
            'host' => '127.0.0.1',
            'port' => 6379,
            'timeout' => 0.1,
            'read_timeout' => '10',
            'persistent_connections' => false,
            'password' => null,
            'user' => null,
        ]);

        $options = $this->innerOptions(new PhpRedisEvalShaClient(['host' => 'redis.internal', 'password' => 'secret']));
        Assert::same($options['host'], 'redis.internal');
        Assert::same($options['password'], 'secret');
        Assert::same($options['port'], 6379);
    }

    /**
     * @return array<string, mixed>
     */
    private function innerOptions(PhpRedisEvalShaClient $client): array
    {
        $inner = (new \ReflectionProperty(AbstractEvalShaClient::class, 'client'))->getValue($client);
        Assert::instanceOf($inner, PHPRedis::class);

        /** @var array<string, mixed> */
        return (new \ReflectionProperty(PHPRedis::class, 'options'))->getValue($inner);
    }
}
