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

    #[\Override]
    public function executeCommand(CommandInterface $command)
    {
        $this->commands[] = [$command->getId(), $command->getArguments()];

        $reply = array_shift($this->replies);

        return $reply === null ? 'OK' : new Error($reply);
    }

    #[\Override]
    public function getParameters()
    {
        return $this->parameters;
    }

    #[\Override]
    public function connect(): void {}

    #[\Override]
    public function disconnect(): void {}

    #[\Override]
    public function isConnected(): bool
    {
        return true;
    }

    #[\Override]
    public function writeRequest(CommandInterface $command): void {}

    #[\Override]
    public function readResponse(CommandInterface $command)
    {
        return 'OK';
    }

    #[\Override]
    public function write(string $buffer): void {}

    #[\Override]
    public function __toString(): string
    {
        return 'fake';
    }

    #[\Override]
    public function getResource()
    {
        return null;
    }

    #[\Override]
    public function getClientId(): ?int
    {
        return null;
    }

    #[\Override]
    public function addConnectCommand(CommandInterface $command): void {}

    #[\Override]
    public function read()
    {
        return 'OK';
    }

    #[\Override]
    public function hasDataToRead(): bool
    {
        return false;
    }
}
