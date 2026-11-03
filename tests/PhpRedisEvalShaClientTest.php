<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Tests;

use Prometheus\Storage\RedisClients\PHPRedis;
use Prometheus\Storage\RedisClients\RedisClientException;
use Rasuvaeff\Yii3MetricsPrometheus\Internal\AbstractEvalShaClient;
use Rasuvaeff\Yii3MetricsPrometheus\Internal\PhpRedisEvalShaClient;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

/**
 * Drives the client through FakeRedis, a `\Redis` subclass that never
 * connects, so the tests run the same with or without ext-redis.
 */
#[Test]
#[Covers(PhpRedisEvalShaClient::class)]
final class PhpRedisEvalShaClientTest
{
    private const string NOSCRIPT = 'NOSCRIPT No matching script. Please use EVAL.';

    #[BeforeTest]
    public function installRedis(): void
    {
        RedisStub::install();
    }

    public function sendsEvalShaOnceAfterClearingStaleErrors(): void
    {
        $redis = new FakeRedis();
        $redis->lastError = 'NOSCRIPT stale error from an earlier command';

        $this->client($redis)->eval('return 1', ['key', 'arg'], 1);

        Assert::same($redis->calls, [
            ['clearLastError', []],
            ['evalSha', [sha1('return 1'), ['key', 'arg'], 1]],
        ]);
    }

    public function fallsBackToEvalOnNoscriptAfterClearingTheError(): void
    {
        $redis = new FakeRedis();
        $redis->errorAfterEvalSha = self::NOSCRIPT;

        $this->client($redis)->eval('return 1', ['key'], 1);

        Assert::same($redis->calls, [
            ['clearLastError', []],
            ['evalSha', [sha1('return 1'), ['key'], 1]],
            ['clearLastError', []],
            ['eval', ['return 1', ['key'], 1]],
        ]);
    }

    public function failsWhenEvalShaRepliesWithAnotherError(): void
    {
        $redis = new FakeRedis();
        $redis->errorAfterEvalSha = 'ERR wrong number of arguments';

        try {
            $this->client($redis)->eval('return 1');
            Assert::fail('expected a RedisClientException');
        } catch (RedisClientException $exception) {
            Assert::same($exception->getMessage(), 'ERR wrong number of arguments');
        }

        Assert::same(\count($redis->calls), 2);
    }

    public function failsWhenTheFallbackEvalRepliesWithAnError(): void
    {
        $redis = new FakeRedis();
        $redis->errorAfterEvalSha = self::NOSCRIPT;
        $redis->errorAfterEval = 'ERR Error compiling script';

        try {
            $this->client($redis)->eval('return 1');
            Assert::fail('expected a RedisClientException');
        } catch (RedisClientException $exception) {
            Assert::same($exception->getMessage(), 'ERR Error compiling script');
        }
    }

    public function wrapsConnectionFailuresOfEvalSha(): void
    {
        $redis = new FakeRedis();
        $failure = new \RedisException('connection lost', 7);
        $redis->throwOnEvalSha = $failure;

        try {
            $this->client($redis)->eval('return 1');
            Assert::fail('expected a RedisClientException');
        } catch (RedisClientException $exception) {
            Assert::same($exception->getMessage(), 'connection lost');
            Assert::same($exception->getCode(), 7);
            Assert::same($exception->getPrevious(), $failure);
        }
    }

    public function wrapsConnectionFailuresOfTheFallbackEval(): void
    {
        $redis = new FakeRedis();
        $redis->errorAfterEvalSha = self::NOSCRIPT;
        $failure = new \RedisException('connection reset', 9);
        $redis->throwOnEval = $failure;

        try {
            $this->client($redis)->eval('return 1');
            Assert::fail('expected a RedisClientException');
        } catch (RedisClientException $exception) {
            Assert::same($exception->getMessage(), 'connection reset');
            Assert::same($exception->getCode(), 9);
            Assert::same($exception->getPrevious(), $failure);
        }
    }

    public function mergesOptionsOverPromphpDefaults(): void
    {
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
     * A client whose `\Redis` is the given double. The constructor is skipped
     * because it creates its own readonly `\Redis`; only the eval paths, which
     * talk to that object directly, are exercised through this.
     */
    private function client(FakeRedis $redis): PhpRedisEvalShaClient
    {
        $client = (new \ReflectionClass(PhpRedisEvalShaClient::class))->newInstanceWithoutConstructor();
        (function () use ($redis): void {
            $this->redis = $redis;
        })->call($client);

        return $client;
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
