<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MetricsPrometheus\Internal;

use Prometheus\Storage\RedisClients\RedisClient;

/**
 * Adds per-client Lua script caching to a promphp Redis client contract.
 *
 * @internal
 */
abstract class AbstractEvalShaClient implements RedisClient
{
    /** @var array<string, string> */
    private array $loadedScripts = [];

    #[\Override]
    public function eval(string $script, array $args = [], int $num_keys = 0): void
    {
        $sha = sha1($script);

        if (($this->loadedScripts[$sha] ?? null) !== $script) {
            $this->scriptLoad($script);
            $this->loadedScripts[$sha] = $script;
        }

        try {
            $this->evalSha($sha, $args, $num_keys);
        } catch (\Throwable $exception) {
            if (!$this->isNoScript($exception)) {
                throw $exception;
            }

            // Redis can lose its script cache after SCRIPT FLUSH or a restart.
            // Fall back for this call and reload on the next one.
            unset($this->loadedScripts[$sha]);
            $this->rawEval($script, $args, $num_keys);
        }
    }

    abstract protected function scriptLoad(string $script): void;

    /**
     * @param mixed[] $args
     */
    abstract protected function evalSha(string $sha, array $args, int $num_keys): void;

    /**
     * @param mixed[] $args
     */
    abstract protected function rawEval(string $script, array $args, int $num_keys): void;

    protected function isNoScript(\Throwable $exception): bool
    {
        return str_contains(strtoupper($exception->getMessage()), 'NOSCRIPT');
    }
}
