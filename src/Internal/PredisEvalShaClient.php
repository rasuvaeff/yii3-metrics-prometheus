<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Internal;

use Predis\Client;
use Predis\Response\ServerException;
use Prometheus\Storage\RedisClients\Predis as PromphpPredisClient;

/**
 * Predis throws a ServerException on Redis error replies, so a NOSCRIPT reply
 * is detected from the caught exception: it falls back to EVAL, any other
 * error propagates.
 *
 * @internal
 */
final class PredisEvalShaClient extends AbstractEvalShaClient
{
    /**
     * Mirrors promphp's Predis adapter defaults; pinned to the vendor values by
     * EvalShaPromphpDefaultsTest.
     */
    private const array DEFAULT_PARAMETERS = [
        'scheme' => 'tcp',
        'host' => '127.0.0.1',
        'port' => 6379,
        'timeout' => 0.1,
        'read_write_timeout' => 10,
        'persistent' => false,
        'password' => null,
        'username' => null,
    ];

    /**
     * @var array<string, mixed>
     */
    private const array DEFAULT_OPTIONS = [
        'prefix' => '',
        'throw_errors' => true,
    ];

    private readonly Client $client;

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $options
     */
    public function __construct(array $parameters, array $options = [])
    {
        $this->client = new Client(
            array_merge(self::DEFAULT_PARAMETERS, $parameters),
            array_merge(self::DEFAULT_OPTIONS, $options),
        );

        parent::__construct(PromphpPredisClient::fromExistingConnection($this->client));
    }

    /**
     * @param mixed[] $args
     */
    #[\Override]
    protected function evalSha(string $sha, array $args, int $num_keys): bool
    {
        try {
            $this->client->evalsha($sha, $num_keys, ...array_values($args));
        } catch (ServerException $exception) {
            if (!self::isNoScript($exception->getMessage())) {
                throw $exception;
            }

            return false;
        }

        return true;
    }
}
