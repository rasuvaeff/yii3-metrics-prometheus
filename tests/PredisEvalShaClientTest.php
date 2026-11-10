<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Tests;

use Predis\Client;
use Predis\Connection\ParametersInterface;
use Predis\Response\ServerException;
use Rasuvaeff\Yii3MetricsPrometheus\Internal\PredisEvalShaClient;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(PredisEvalShaClient::class)]
final class PredisEvalShaClientTest
{
    public function sendsEvalShaFirstWithTheScriptDigestAndKeyCount(): void
    {
        $connection = null;
        $client = $this->client($connection);

        $client->eval('return 1', ['key', 'arg'], 1);

        Assert::same($connection?->commands, [
            ['EVALSHA', [sha1('return 1'), 1, 'key', 'arg']],
        ]);
    }

    public function flattensNamedArgumentsIntoPositionalOnes(): void
    {
        $connection = null;
        $client = $this->client($connection);

        $client->eval('return 1', ['first' => 'a', 'second' => 'b'], 0);

        Assert::same($connection?->commands, [
            ['EVALSHA', [sha1('return 1'), 0, 'a', 'b']],
        ]);
    }

    public function fallsBackToPlainEvalOnNoscript(): void
    {
        $connection = null;
        $client = $this->client($connection, ['NOSCRIPT No matching script. Please use EVAL.']);

        $client->eval('return 1', ['key'], 1);

        Assert::same($connection?->commands, [
            ['EVALSHA', [sha1('return 1'), 1, 'key']],
            ['EVAL', ['return 1', 1, 'key']],
        ]);
    }

    public function propagatesOtherServerErrorsWithoutFallback(): void
    {
        $connection = null;
        $client = $this->client($connection, ['ERR something broke']);

        try {
            $client->eval('return 1', [], 0);
            Assert::fail('expected a ServerException');
        } catch (ServerException $exception) {
            Assert::same($exception->getMessage(), 'ERR something broke');
        }

        Assert::same($connection?->commands, [
            ['EVALSHA', [sha1('return 1'), 0]],
        ]);
    }

    public function mergesConnectionParametersOverPromphpDefaults(): void
    {
        $connection = null;
        $this->client($connection, parameters: ['host' => 'redis.internal', 'port' => 6380]);

        $parameters = $connection?->parameters;
        Assert::instanceOf($parameters, ParametersInterface::class);
        Assert::same($parameters->host, 'redis.internal');
        Assert::same($parameters->port, 6380);
        Assert::same($parameters->scheme, 'tcp');
        Assert::same($parameters->timeout, 0.1);
        Assert::same($parameters->read_write_timeout, 10);
    }

    public function appliesDefaultParametersWhenNoneAreGiven(): void
    {
        $connection = null;
        $this->client($connection);

        $parameters = $connection?->parameters;
        Assert::instanceOf($parameters, ParametersInterface::class);
        Assert::same($parameters->host, '127.0.0.1');
        Assert::same($parameters->port, 6379);
    }

    public function mergesClientOptionsOverDefaults(): void
    {
        $connection = null;
        $client = $this->client($connection, options: ['prefix' => 'app:']);

        $predis = $this->innerPredis($client);
        Assert::same($predis->getOptions()->prefix->getPrefix(), 'app:');
        Assert::true($predis->getOptions()->exceptions);
    }

    public function defaultsToAnEmptyKeyPrefix(): void
    {
        $connection = null;
        $client = $this->client($connection);

        Assert::same($this->innerPredis($client)->getOptions()->prefix->getPrefix(), '');
    }

    /**
     * @param list<string> $replies
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $options
     */
    private function client(
        ?FakePredisConnection &$connection,
        array $replies = [],
        array $parameters = [],
        array $options = [],
    ): PredisEvalShaClient {
        $options['connections'] = [
            'tcp' => static function (ParametersInterface $parameters) use (&$connection, $replies): FakePredisConnection {
                $connection = new FakePredisConnection($parameters);
                $connection->replies = $replies;

                return $connection;
            },
        ];

        $client = new PredisEvalShaClient($parameters, $options);
        $this->innerPredis($client)->getConnection();

        return $client;
    }

    private function innerPredis(PredisEvalShaClient $client): Client
    {
        $property = new \ReflectionProperty(PredisEvalShaClient::class, 'predis');

        /** @var Client */
        return $property->getValue($client);
    }
}
