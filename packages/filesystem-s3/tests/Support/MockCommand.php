<?php

declare(strict_types=1);

namespace Marko\Filesystem\S3\Tests\Support;

use ArrayIterator;
use Aws\CommandInterface;
use Aws\HandlerList;
use Traversable;

/**
 * Mock implementation of AWS CommandInterface for testing.
 *
 * Method signatures intentionally match the AWS SDK's untyped interface.
 *
 * @phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint
 * @phpcs:disable SlevomatCodingStandard.TypeHints.ReturnTypeHint
 */
class MockCommand implements CommandInterface
{
    public function __construct(
        private readonly string $commandName = 'MockCommand',
    ) {}

    public function toArray()
    {
        return [];
    }

    public function getName()
    {
        return $this->commandName;
    }

    public function hasParam(
        $name,
    ) {
        return false;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator([]);
    }

    public function offsetExists(
        mixed $offset,
    ): bool {
        return false;
    }

    public function offsetGet(
        mixed $offset,
    ): mixed {
        return null;
    }

    public function offsetSet(
        mixed $offset,
        mixed $value,
    ): void {}

    public function offsetUnset(mixed $offset): void {}

    public function count(): int
    {
        return 0;
    }

    public function getHandlerList()
    {
        return new HandlerList();
    }
}
