<?php

declare(strict_types=1);

namespace Marko\Core\Command;

/**
 * Parses and provides access to command-line arguments.
 *
 * The raw argv is parsed once, at construction, into positional arguments and options:
 *
 * - `--name=value` and `-n=value` always carry a value.
 * - `--name value` and `-n value` take the next token as the value when it does not start
 *   with `-`, unless `name` is a declared flag. Declared flags never consume the next token.
 * - `--name` / `-n` followed by another option, by `--` or by the end of input is `'true'`.
 * - `--` ends option parsing; every token after it is positional.
 * - Options can repeat (`--queue a --queue b`); `getOption()` returns the last value and
 *   `getOptionValues()` returns them all.
 *
 * Single-character names refer to short options (`-d`), longer names to long options (`--detach`).
 *
 * `--no-interaction` and `--help` are global flags: every command accepts them and they never take
 * a value. `--no-interaction` makes isInteractive() false so nothing asks the person running the
 * command a question; `--help` makes the command runner print the command's help instead of running it.
 */
readonly class Input
{
    private const string OPTION_TERMINATOR = '--';

    /**
     * Flags every command accepts, whether or not it declares them.
     */
    public const array GLOBAL_FLAGS = [self::NO_INTERACTION, self::HELP];

    public const string NO_INTERACTION = 'no-interaction';

    public const string HELP = 'help';

    /**
     * @var list<string>
     */
    private array $positionals;

    /**
     * Option values keyed by their prefixed form (`-d`, `--queue`).
     *
     * @var array<string, list<string>>
     */
    private array $options;

    /**
     * @param array<int, string> $arguments The raw command-line arguments (argv-style)
     * @param list<string> $flags Option names that never take a value (e.g. `force`, `d`)
     */
    public function __construct(
        private array $arguments,
        private array $flags = [],
    ) {
        $positionals = [];
        $options = [];
        $tokens = array_slice($this->arguments, 2);
        $count = count($tokens);
        $optionsEnded = false;

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if ($optionsEnded || !$this->isOptionToken($token)) {
                $positionals[] = $token;

                continue;
            }

            if ($token === self::OPTION_TERMINATOR) {
                $optionsEnded = true;

                continue;
            }

            $equalsPosition = strpos($token, '=');

            if ($equalsPosition !== false) {
                $options[substr($token, 0, $equalsPosition)][] = substr($token, $equalsPosition + 1);

                continue;
            }

            $next = $tokens[$index + 1] ?? null;

            if ($next !== null && !$this->isFlag($token) && !str_starts_with($next, '-')) {
                $options[$token][] = $next;
                $index++;

                continue;
            }

            $options[$token][] = 'true';
        }

        $this->positionals = $positionals;
        $this->options = $options;
    }

    /**
     * Returns a copy of this input re-parsed with the given value-less flags declared.
     *
     * @param list<string> $flags
     */
    public function withFlags(
        array $flags,
    ): self {
        return new self($this->arguments, $flags);
    }

    /**
     * Returns the command name (second argument after script name).
     */
    public function getCommand(): ?string
    {
        return $this->arguments[1] ?? null;
    }

    /**
     * Returns the positional arguments after the command name. Option tokens and their values are excluded.
     *
     * @return list<string>
     */
    public function getArguments(): array
    {
        return $this->positionals;
    }

    /**
     * Checks if a positional argument exists at the given index.
     */
    public function hasArgument(
        int $index,
    ): bool {
        return isset($this->positionals[$index]);
    }

    /**
     * Returns the positional argument at the given index, or null if not found.
     */
    public function getArgument(
        int $index,
    ): ?string {
        return $this->positionals[$index] ?? null;
    }

    /**
     * Checks if an option exists (e.g., --verbose, --queue=value, --queue value, -d, -p=8000).
     *
     * Single-char names match short options (-x), multi-char names match long options (--name).
     */
    public function hasOption(
        string $name,
    ): bool {
        return isset($this->options[$this->optionKey($name)]);
    }

    /**
     * Returns the value of an option, or null if not found.
     * For boolean flags (--verbose, -d), returns "true".
     * For value options (--queue=emails, --queue emails, -p=8000, -p 8000), returns the value.
     * For repeated options, returns the last value.
     *
     * Single-char names match short options, multi-char names match long options.
     */
    public function getOption(
        string $name,
    ): ?string {
        $values = $this->getOptionValues($name);

        return $values === [] ? null : $values[array_key_last($values)];
    }

    /**
     * Returns every value given for an option, in order, or an empty list if not found.
     *
     * @return list<string>
     */
    public function getOptionValues(
        string $name,
    ): array {
        return $this->options[$this->optionKey($name)] ?? [];
    }

    /**
     * Returns the names of the options that were given, in first-seen order, in the form hasOption() takes:
     * `--queue` is `queue`, `-d` is `d`.
     *
     * A token hasOption() cannot address (`--x`, a long option with a one-character name, or `-ab`, a short
     * option with a longer name) keeps its prefix, so it never matches a declared option name.
     *
     * @return list<string>
     */
    public function optionNames(): array
    {
        return array_map(
            fn (string $token): string => $this->optionName($token),
            $this->optionTokens(),
        );
    }

    /**
     * Returns the given options whose names are not in $acceptedNames, as they were typed (`--dry-run`, `-x`).
     *
     * @param list<string> $acceptedNames Option names in the form hasOption() takes
     * @return list<string>
     */
    public function undeclaredOptions(
        array $acceptedNames,
    ): array {
        return array_values(array_filter(
            $this->optionTokens(),
            fn (string $token): bool => !in_array($this->optionName($token), $acceptedNames, true),
        ));
    }

    /**
     * The given option tokens. PHP turns a numeric array key such as `-1` into an int, so each is cast back.
     *
     * @return list<string>
     */
    private function optionTokens(): array
    {
        return array_map(strval(...), array_keys($this->options));
    }

    private function optionName(
        string $token,
    ): string {
        $name = ltrim($token, '-');

        return $this->optionKey($name) === $token ? $name : $token;
    }

    private function optionKey(
        string $name,
    ): string {
        return strlen($name) === 1 ? "-$name" : "--$name";
    }

    private function isOptionToken(
        string $token,
    ): bool {
        return str_starts_with($token, '-') && $token !== '-';
    }

    /**
     * Whether a person may be asked questions: false when --no-interaction was passed.
     *
     * Whether a terminal is attached is a separate question, answered by the confirmation prompter.
     */
    public function isInteractive(): bool
    {
        return !$this->hasOption(self::NO_INTERACTION);
    }

    private function isFlag(
        string $token,
    ): bool {
        return array_any(
            [...self::GLOBAL_FLAGS, ...$this->flags],
            fn (string $flag): bool => $this->optionKey($flag) === $token,
        );
    }
}
