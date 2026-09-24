<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Tests;

use Rasuvaeff\Yii3MetricsPrometheus\Internal\AbstractEvalShaClient;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(AbstractEvalShaClient::class)]
final class EvalShaClientTest
{
    public function loadsEachScriptOnceAndUsesItsSha(): void
    {
        $client = new RecordingEvalShaClient();

        $client->eval('return ARGV[1]', ['value'], 0);
        $client->eval('return ARGV[1]', ['value'], 0);

        Assert::same($client->loaded, ['return ARGV[1]']);
        Assert::same($client->shaCalls, [[sha1('return ARGV[1]'), ['value'], 0], [sha1('return ARGV[1]'), ['value'], 0]]);
        Assert::same($client->evalCalls, []);
    }

    public function fallsBackToEvalAfterNoscriptAndReloadsNextTime(): void
    {
        $client = new RecordingEvalShaClient(noscript: true);

        $client->eval('return 1');
        $client->eval('return 1');

        Assert::same($client->loaded, ['return 1', 'return 1']);
        Assert::same($client->evalCalls, [['return 1', [], 0], ['return 1', [], 0]]);
    }

    public function propagatesOtherRedisErrors(): void
    {
        $client = new RecordingEvalShaClient(error: new \RuntimeException('connection lost'));

        try {
            $client->eval('return 1');
            Assert::fail('expected the Redis error');
        } catch (\RuntimeException $exception) {
            Assert::same($exception->getMessage(), 'connection lost');
        }
    }
}

/** @internal */
final class RecordingEvalShaClient extends AbstractEvalShaClient
{
    /** @var list<string> */
    public array $loaded = [];

    /** @var list<array{string, list<string>, int}> */
    public array $shaCalls = [];

    /** @var list<array{string, list<string>, int}> */
    public array $evalCalls = [];

    public function __construct(
        private readonly bool $noscript = false,
        private readonly ?\Throwable $error = null,
    ) {}

    #[\Override]
    public function getPrefix(): ?string
    {
        return null;
    }

    #[\Override]
    public function set(string $key, mixed $value, mixed $options = null): bool
    {
        return true;
    }

    #[\Override]
    public function setNx(string $key, mixed $value): void {}

    #[\Override]
    public function sMembers(string $key): array
    {
        return [];
    }

    #[\Override]
    public function hGetAll(string $key): array|false
    {
        return [];
    }

    #[\Override]
    public function keys(string $pattern): array
    {
        return [];
    }

    #[\Override]
    public function get(string $key): string|false
    {
        return false;
    }

    #[\Override]
    public function del(array|string $key, string ...$other_keys): void {}

    #[\Override]
    public function ensureOpenConnection(): void {}

    #[\Override]
    protected function scriptLoad(string $script): void
    {
        $this->loaded[] = $script;
    }

    #[\Override]
    protected function evalSha(string $sha, array $args, int $num_keys): void
    {
        if ($this->error instanceof \Throwable) {
            throw $this->error;
        }
        if ($this->noscript) {
            throw new \RuntimeException('NoScRiPt No matching script');
        }

        $this->shaCalls[] = [$sha, array_values(array_map(static fn(mixed $value): string => (string) $value, $args)), $num_keys];
    }

    #[\Override]
    protected function rawEval(string $script, array $args, int $num_keys): void
    {
        $this->evalCalls[] = [$script, array_values(array_map(static fn(mixed $value): string => (string) $value, $args)), $num_keys];
    }
}
