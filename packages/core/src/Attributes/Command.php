<?php

declare(strict_types=1);

namespace Marko\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Command
{
    /**
     * @param list<string> $aliases
     * @param list<string> $flags Value-less options (e.g. `force`, `d`) that never consume the next token
     * @param bool $destructive The command changes or deletes stored state (database rows, cache entries, queued
     *                          jobs, log files...). Callers that run commands on someone else's behalf, such as the
     *                          MCP run_console_command tool, refuse destructive commands unless explicitly allowed
     * @param list<string> $options Options that take a value (e.g. `queue`, `p`). Together with $flags and the global
     *                              flags (`--no-interaction`, `--help`) they are the only options the command accepts:
     *                              the command runner refuses any other option before the command executes
     */
    public function __construct(
        public string $name,
        public string $description = '',
        public array $aliases = [],
        public array $flags = [],
        public bool $destructive = false,
        public array $options = [],
    ) {}
}
