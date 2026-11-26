<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Migration;

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Plugin\PluginInterceptedInterface;
use Marko\Core\Plugin\PluginRegistry;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;

/**
 * The shape a plugin on ConnectionInterface gives the container: an interface
 * wrapper that implements only ConnectionInterface and PluginInterceptedInterface.
 */
class PluginProxiedConnection implements ConnectionInterface, PluginInterceptedInterface
{
    /**
     * Statements that went through the proxy.
     *
     * @var list<string>
     */
    public array $seen = [];

    public function __construct(
        private readonly ConnectionInterface $target,
    ) {}

    public function initInterception(
        object $pluginTarget,
        string $pluginTargetClass,
        ContainerInterface $pluginContainer,
        PluginRegistry $pluginRegistry,
    ): void {}

    public function getPluginTarget(): object
    {
        return $this->target;
    }

    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        $this->seen[] = $sql;

        return $this->target->execute($sql, $bindings);
    }

    public function query(
        string $sql,
        array $bindings = [],
    ): array {
        return $this->target->query($sql, $bindings);
    }

    public function connect(): void
    {
        $this->target->connect();
    }

    public function disconnect(): void
    {
        $this->target->disconnect();
    }

    public function isConnected(): bool
    {
        return $this->target->isConnected();
    }

    public function prepare(
        string $sql,
    ): StatementInterface {
        return $this->target->prepare($sql);
    }

    public function lastInsertId(): int
    {
        return $this->target->lastInsertId();
    }

    public function driverName(): string
    {
        return $this->target->driverName();
    }

    public function supportsReturning(): bool
    {
        return $this->target->supportsReturning();
    }

    public function quoteIdentifier(
        string $identifier,
    ): string {
        return $this->target->quoteIdentifier($identifier);
    }
}
