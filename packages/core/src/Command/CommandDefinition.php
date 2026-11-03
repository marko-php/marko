<?php

declare(strict_types=1);

namespace Marko\Core\Command;

/**
 * Value object representing a discovered command.
 */
readonly class CommandDefinition
{
    /**
     * @param list<string> $aliases
     * @param list<string> $flags
     * @param list<string> $options
     */
    public function __construct(
        public string $commandClass,
        public string $name,
        public string $description = '',
        public array $aliases = [],
        public array $flags = [],
        public bool $destructive = false,
        public array $options = [],
    ) {}

    /**
     * Every option the command accepts: its value-taking options, its flags and the global flags.
     *
     * @return list<string>
     */
    public function acceptedOptions(): array
    {
        return array_values(array_unique([...$this->options, ...$this->flags, ...Input::GLOBAL_FLAGS]));
    }

    /**
     * The accepted options as they are typed: `--queue=<value>` for a value-taking option, `--force` / `-f` for a flag.
     *
     * @return list<string>
     */
    public function acceptedOptionUsages(): array
    {
        return array_map(
            function (string $name): string {
                $token = strlen($name) === 1 ? "-$name" : "--$name";

                return in_array($name, [...$this->flags, ...Input::GLOBAL_FLAGS], true) ? $token : "$token=<value>";
            },
            $this->acceptedOptions(),
        );
    }
}
