<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Tests;

/**
 * Scripted `\Redis` double: never connects, records what the client sends and
 * answers EVALSHA / EVAL with the configured last-error or exception.
 *
 * @internal
 */
final class FakeRedis extends \Redis
{
    /** @var list<array{string, list<mixed>}> */
    public array $calls = [];

    public ?string $lastError = null;

    public ?string $errorAfterEvalSha = null;

    public ?string $errorAfterEval = null;

    public ?\RedisException $throwOnEvalSha = null;

    public ?\RedisException $throwOnEval = null;

    public function clearLastError(): bool
    {
        $this->calls[] = ['clearLastError', []];
        $this->lastError = null;

        return true;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @param array<mixed> $args
     */
    public function evalSha(string $sha, array $args = [], int $numKeys = 0): mixed
    {
        $this->calls[] = ['evalSha', [$sha, $args, $numKeys]];

        if ($this->throwOnEvalSha instanceof \RedisException) {
            throw $this->throwOnEvalSha;
        }

        $this->lastError = $this->errorAfterEvalSha;

        return false;
    }

    /**
     * @param array<mixed> $args
     */
    public function eval(string $script, array $args = [], int $numKeys = 0): mixed
    {
        $this->calls[] = ['eval', [$script, $args, $numKeys]];

        if ($this->throwOnEval instanceof \RedisException) {
            throw $this->throwOnEval;
        }

        $this->lastError = $this->errorAfterEval;

        return false;
    }
}
