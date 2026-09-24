<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Tests;

use Prometheus\Storage\RedisClients\RedisClient;
use Prometheus\Storage\RedisClients\RedisClientException;
use Rasuvaeff\Yii3MetricsPrometheus\Internal\AbstractEvalShaClient;
use Rasuvaeff\Yii3MetricsPrometheus\Internal\PhpRedisEvalShaClient;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(AbstractEvalShaClient::class)]
final class EvalShaClientTest
{
    public function triesEvalShaFirstWithoutLoadingTheScript(): void
    {
        $client = new RecordingEvalShaClient();

        $client->eval('return ARGV[1]', ['value'], 0);
        $client->eval('return ARGV[1]', ['value'], 0);

        Assert::same($client->shaCalls, [
            [sha1('return ARGV[1]'), ['value'], 0],
            [sha1('return ARGV[1]'), ['value'], 0],
        ]);
        Assert::same($client->inner->evalCalls, []);
    }

    public function fallsBackToEvalOnNoscriptAndTriesEvalShaAgainNextTime(): void
    {
        $client = new RecordingEvalShaClient(noscript: true);

        $client->eval('return 1', ['a'], 1);
        $client->eval('return 1', ['a'], 1);

        Assert::same($client->inner->evalCalls, [
            ['return 1', ['a'], 1],
            ['return 1', ['a'], 1],
        ]);
    }

    public function propagatesRedisErrorsFromEvalSha(): void
    {
        $client = new RecordingEvalShaClient(error: new \RuntimeException('connection lost'));

        try {
            $client->eval('return 1');
            Assert::fail('expected the Redis error');
        } catch (\RuntimeException $exception) {
            Assert::same($exception->getMessage(), 'connection lost');
        }
    }

    public function detectsNoscriptMessagesCaseInsensitively(): void
    {
        Assert::true(AbstractEvalShaClient::isNoScript('NoScRiPt No matching script. Please use EVAL.'));
        Assert::false(AbstractEvalShaClient::isNoScript('ERR unknown command'));
    }

    public function classifiesPhpRedisLastError(): void
    {
        Assert::true(PhpRedisEvalShaClient::classifyLastError(null));
        Assert::true(PhpRedisEvalShaClient::classifyLastError(''));
        Assert::false(PhpRedisEvalShaClient::classifyLastError('NOSCRIPT No matching script. Please use EVAL.'));

        try {
            PhpRedisEvalShaClient::classifyLastError('ERR wrong number of arguments');
            Assert::fail('expected a RedisClientException');
        } catch (RedisClientException $exception) {
            Assert::same($exception->getMessage(), 'ERR wrong number of arguments');
        }
    }

    public function delegatesStorageCommandsToTheInnerClient(): void
    {
        $client = new RecordingEvalShaClient();
        $inner = $client->inner;

        Assert::null($client->getPrefix());
        Assert::true($client->set('k', 'v', ['nx']));
        $client->setNx('k', 'v');
        Assert::same($client->sMembers('k'), ['m']);
        Assert::same($client->hGetAll('k'), ['f' => '1']);
        Assert::same($client->keys('P*'), ['PROM_X']);
        Assert::same($client->get('k'), 'v');
        $client->del('k', 'j');
        $client->ensureOpenConnection();

        Assert::same($inner->calls, [
            ['getPrefix', []],
            ['set', ['k', 'v', ['nx']]],
            ['setNx', ['k', 'v']],
            ['sMembers', ['k']],
            ['hGetAll', ['k']],
            ['keys', ['P*']],
            ['get', ['k']],
            ['del', ['k', 'j']],
            ['ensureOpenConnection', []],
        ]);
    }
}

/** @internal */
final class RecordingInnerClient implements RedisClient
{
    /** @var list<array{string, array<int, mixed>}> */
    public array $calls = [];

    /** @var list<array{string, list<string>, int}> */
    public array $evalCalls = [];

    #[\Override]
    public function eval(string $script, array $args = [], int $num_keys = 0): void
    {
        $this->evalCalls[] = [$script, $args, $num_keys];
    }

    #[\Override]
    public function getPrefix(): ?string
    {
        $this->calls[] = ['getPrefix', []];

        return null;
    }

    #[\Override]
    public function set(string $key, mixed $value, mixed $options = null): bool
    {
        $this->calls[] = ['set', [$key, $value, $options]];

        return true;
    }

    #[\Override]
    public function setNx(string $key, mixed $value): void
    {
        $this->calls[] = ['setNx', [$key, $value]];
    }

    #[\Override]
    public function sMembers(string $key): array
    {
        $this->calls[] = ['sMembers', [$key]];

        return ['m'];
    }

    #[\Override]
    public function hGetAll(string $key): array|false
    {
        $this->calls[] = ['hGetAll', [$key]];

        return ['f' => '1'];
    }

    #[\Override]
    public function keys(string $pattern): array
    {
        $this->calls[] = ['keys', [$pattern]];

        return ['PROM_X'];
    }

    #[\Override]
    public function get(string $key): string|false
    {
        $this->calls[] = ['get', [$key]];

        return 'v';
    }

    #[\Override]
    public function del(array|string $key, string ...$other_keys): void
    {
        $this->calls[] = ['del', [$key, ...$other_keys]];
    }

    #[\Override]
    public function ensureOpenConnection(): void
    {
        $this->calls[] = ['ensureOpenConnection', []];
    }
}

/** @internal */
final class RecordingEvalShaClient extends AbstractEvalShaClient
{
    public readonly RecordingInnerClient $inner;

    /** @var list<array{string, list<string>, int}> */
    public array $shaCalls = [];

    public function __construct(
        private readonly bool $noscript = false,
        private readonly ?\Throwable $error = null,
    ) {
        $this->inner = new RecordingInnerClient();

        parent::__construct($this->inner);
    }

    #[\Override]
    protected function evalSha(string $sha, array $args, int $num_keys): bool
    {
        if ($this->error instanceof \Throwable) {
            throw $this->error;
        }

        $this->shaCalls[] = [$sha, $args, $num_keys];

        return !$this->noscript;
    }
}
