<?php

declare(strict_types=1);

namespace Marko\Core\Command;

/**
 * Value object representing a discovered command.
 */
readonly class CommandDefinition
{
    public function __construct(
        public string $commandClass,
        public string $name,
        public string $description = '',
    ) {}
}
