<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Tests;

use Predis\Command\CommandInterface;
use Predis\Connection\NodeConnectionInterface;
use Predis\Connection\ParametersInterface;
use Predis\Response\Error;

/**
 * @internal
 */
final class FakePredisConnection implements NodeConnectionInterface
{
    /** @var list<array{string, list<mixed>}> */
    public array $commands = [];

    /** @var list<string|null> replies handed out in order; null means a plain OK */
    public array $replies = [];

    public function __construct(
        public readonly ParametersInterface $parameters,
    ) {}

    public function executeCommand(CommandInterface $command)
    {
        $this->commands[] = [$command->getId(), $command->getArguments()];

        $reply = array_shift($this->replies);

        return $reply === null ? 'OK' : new Error($reply);
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    public function connect(): void {}

    public function disconnect(): void {}

    public function isConnected(): bool
    {
        return true;
    }

    public function writeRequest(CommandInterface $command): void {}

    public function readResponse(CommandInterface $command)
    {
        return 'OK';
    }

    public function write(string $buffer): void {}

    public function __toString(): string
    {
        return 'fake';
    }

    public function getResource()
    {
        return null;
    }

    public function getClientId(): ?int
    {
        return null;
    }

    public function addConnectCommand(CommandInterface $command): void {}

    public function read()
    {
        return 'OK';
    }

    public function hasDataToRead(): bool
    {
        return false;
    }
}
