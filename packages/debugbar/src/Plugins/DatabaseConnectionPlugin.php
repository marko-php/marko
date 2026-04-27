<?php

declare(strict_types=1);

namespace Marko\Debugbar\Plugins;

use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Debugbar\Debugbar;

#[Plugin(target: ConnectionInterface::class)]
class DatabaseConnectionPlugin
{
    /**
     * @var array<string, list<float>>
     */
    private array $started = [];

    public function __construct(
        private readonly Debugbar $debugbar,
    ) {}

    /**
     * @param array<mixed> $bindings
     */
    #[Before(method: 'query')]
    public function beforeQuery(
        string $sql,
        array $bindings = [],
    ): void
    {
        $this->start('query', $sql);
    }

    /**
     * @param array<array<string, mixed>> $result
     * @param array<mixed> $bindings
     * @return array<array<string, mixed>>
     */
    #[After(method: 'query')]
    public function afterQuery(
        array $result,
        string $sql,
        array $bindings = [],
    ): array
    {
        $this->finish('query', $sql, $bindings, count($result));

        return $result;
    }

    /**
     * @param array<mixed> $bindings
     */
    #[Before(method: 'execute')]
    public function beforeExecute(
        string $sql,
        array $bindings = [],
    ): void
    {
        $this->start('execute', $sql);
    }

    /**
     * @param array<mixed> $bindings
     */
    #[After(method: 'execute')]
    public function afterExecute(
        int $result,
        string $sql,
        array $bindings = [],
    ): int
    {
        $this->finish('execute', $sql, $bindings, $result);

        return $result;
    }

    private function start(
        string $type,
        string $sql,
    ): void
    {
        if (! $this->debugbar->isEnabled()) {
            return;
        }

        $this->started[$this->key($type, $sql)][] = microtime(true);
    }

    /**
     * @param array<mixed> $bindings
     */
    private function finish(
        string $type,
        string $sql,
        array $bindings,
        int $rows,
    ): void
    {
        if (! $this->debugbar->isEnabled()) {
            return;
        }

        $key = $this->key($type, $sql);
        $start = array_pop($this->started[$key]);

        if (! is_float($start)) {
            return;
        }

        $this->debugbar->recordQuery(
            type: $type,
            sql: $sql,
            bindings: $bindings,
            start: $start,
            durationMs: round((microtime(true) - $start) * 1000, 2),
            rows: $rows,
        );
    }

    private function key(
        string $type,
        string $sql,
    ): string
    {
        return $type.':'.md5($sql);
    }
}
