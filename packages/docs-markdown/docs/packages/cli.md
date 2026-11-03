---
title: marko/cli
description: Global command-line tool for running commands in any Marko project directory.
---

Global command-line tool --- run `marko` from any project directory to execute commands. Install once globally, use in any Marko project. The CLI finds your project root, boots the application, and runs commands registered by your modules. No per-project CLI setup needed.

## Installation

```bash
composer global require marko/cli
```

Ensure Composer's global bin directory is in your PATH.

## Usage

### Running Commands

From any directory within a Marko project:

```bash
marko list                  # Show all available commands
marko module:list           # List installed modules
marko discovery:cache       # Compile the discovery cache (run on deploy)
marko discovery:clear       # Remove the discovery cache
marko cache:clear           # Clear cache (if cache module installed)
marko db:migrate            # Run migrations (if database module installed)
```

### Creating Commands

Register commands in your modules using the `#[Command]` attribute:

```php
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

/** @noinspection PhpUnused */
#[Command(name: 'greet', description: 'Say hello')]
class GreetCommand implements CommandInterface
{
    public function execute(Input $input, Output $output): int
    {
        $name = $input->getArgument(0) ?? 'World';
        $output->writeLine("Hello, $name!");

        return 0; // Exit code
    }
}
```

> **IDE Note:** PhpStorm may report command classes as "unused" since they're discovered via attributes rather than direct instantiation. The `@noinspection PhpUnused` annotation suppresses this false positive.

Run it:

```bash
marko greet
# Hello, World!

marko greet Mark
# Hello, Mark!
```

### Arguments and Options

`Input` parses the command line once into **positional arguments** and **options**. Options can appear before, between, or after arguments --- `getArgument()` only ever sees the positionals.

| Syntax | Result |
|---|---|
| `--queue=emails` | `getOption('queue')` returns `'emails'` |
| `--queue emails` | `getOption('queue')` returns `'emails'` (the next token is the value when it doesn't start with `-`) |
| `--queue` at the end, or followed by another option | `getOption('queue')` returns `'true'` |
| `--queue a --queue b` | `getOption('queue')` returns `'b'` (the last value); `getOptionValues('queue')` returns `['a', 'b']` |
| `-p=8000`, `-p 8000` | `getOption('p')` returns `'8000'` |
| `-d` | `getOption('d')` returns `'true'` |
| `--` | Ends option parsing --- every token after it is a positional argument, even if it starts with `-` |

Single-character names refer to short options (`-d`), longer names to long options (`--detach`). `hasOption('d')` does not match `--detach`, so check both when a command offers both forms.

### Declaring Options and Flags

A command accepts only the options it declares on its `#[Command]` attribute. Declare options that take a value in `options` and value-less boolean flags in `flags`:

```php
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: 'report:send', description: 'Send a report', flags: ['force', 'f'], options: ['to'])]
class SendReportCommand implements CommandInterface
{
    public function execute(Input $input, Output $output): int
    {
        $reportId = $input->getArgument(0);
        $force = $input->hasOption('force') || $input->hasOption('f');
        $recipients = $input->getOptionValues('to');

        // ...

        return 0;
    }
}
```

A bare option followed by a non-dash token takes that token as its value, so a flag must be declared in `flags`: otherwise, in `marko report:send --force 42`, `--force` would swallow `42`. With `force` declared, `marko report:send --force 42 --to a@example.com --to b@example.com` and `marko report:send 42 --force --to=a@example.com --to=b@example.com` behave the same: argument `0` is `'42'`, `force` is set, and `to` has both addresses.

Any other option fails the command before it runs. A typo such as `--forse`, or an option the command never supported, is an error, never an option the command silently ignores:

```text
$ marko report:send 42 --forse

Error: Command 'report:send' does not accept the option '--forse'
  Context: While checking the options given to command 'report:send', before running it
  Suggestion: It accepts: --to=<value>, --force, -f, --no-interaction, --help. Run 'marko report:send --help' for usage. If the command reads this option, declare it in #[Command(options: [...])], or in flags: [...] when it takes no value.
```

Short and long forms are separate names, so declare both when a command offers both (`flags: ['force', 'f']`). Arguments after `--` are positionals and are never checked.

Declared options and flags are applied by the command runner, so they take effect when the command runs through `marko` or the `CommandRunner` (the scheduler and the MCP `run_console_command` tool use it too). In unit tests that call `execute()` directly, pass the same flags to `Input`: `new Input(['marko', 'report:send', '--force', '42'], ['force', 'f'])`. To check options yourself, `$input->optionNames()` lists the names of the options that were given, and `$input->undeclaredOptions(['force', 'to'])` lists the ones not in your list, as they were typed.

Two global flags are accepted by every command without declaring them, and never take a value:

- `--no-interaction` tells commands not to ask questions; see [Asking for Confirmation](/docs/packages/core/#asking-for-confirmation).
- `--help` prints the command's name, aliases, description and accepted options, then exits `0` without running the command:

```text
$ marko report:send --help
report:send
  Send a report

Usage:
  marko report:send [options] [arguments]

Options:
  --to=<value>
  --force
  -f
  --no-interaction
  --help
```

A destructive command's help also says `Changes or deletes stored state.`

### Command Namespacing

Group related commands with colons:

```bash
marko db:migrate
marko db:rollback
marko db:seed
marko cache:clear
marko cache:warmup
```

### How It Works

1. CLI searches upward for a Marko project (looks for `vendor/marko/core`)
2. Loads the project's autoloader
3. Boots the application
4. Runs the requested command

The CLI itself has no commands --- all commands come from modules in your project.

## API Reference

### CommandInterface

```php
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

interface CommandInterface
{
    public function execute(Input $input, Output $output): int;
}
```

### Input

```php
use Marko\Core\Command\Input;

class Input
{
    /**
     * @param array<int, string> $arguments Raw argv (index 0 = script, index 1 = command)
     * @param list<string> $flags Option names that never take a value
     */
    public function __construct(array $arguments, array $flags = []);
    public function withFlags(array $flags): self;
    public function getCommand(): ?string;
    public function getArgument(int $index): ?string;   // Positional arguments only
    public function hasArgument(int $index): bool;
    public function getArguments(): array;              // list<string> of positional arguments
    public function getOption(string $name): ?string;   // Last value, 'true' for a bare flag, null if absent
    public function getOptionValues(string $name): array; // list<string> of every value given
    public function hasOption(string $name): bool;
    public function optionNames(): array;               // list<string> of the given options' names ('queue', 'd')
    public function undeclaredOptions(array $acceptedNames): array; // Given options not in the list, as typed
    public function isInteractive(): bool;              // false when --no-interaction is passed
}
```

### Output

```php
use Marko\Core\Command\Output;

class Output
{
    public function write(string $text): void;
    public function writeLine(string $text): void;
}
```

### Command Attribute

```php
use Marko\Core\Attributes\Command;

#[Command(
    name: 'namespace:name',
    description: 'What it does',
    aliases: ['alias'],          // Optional alternative names
    flags: ['force', 'f'],       // Optional value-less options that never consume the next token
    destructive: true,           // Optional: the command changes or deletes stored state (default false)
    options: ['queue', 'p'],     // Optional options that take a value; any undeclared option is refused
)]
```

### Destructive Commands

Mark a command that changes or deletes stored state (database rows or schema, cache entries, queued jobs, log files, sessions, tokens) with `destructive: true`:

```php
use Marko\Core\Attributes\Command;

#[Command(name: 'report:purge', description: 'Delete old reports', flags: ['force'], destructive: true)]
```

The marker is how callers that run commands on someone else's behalf tell a state-changing command from a read-only one, without guessing from its name or options. The [MCP `run_console_command` tool](/docs/packages/mcp/#run_console_command-safety) refuses every destructive command unless the server operator sets `mcp.console.allow_destructive`. The marker is copied onto the command's `CommandDefinition` as `$definition->destructive`, so your own tooling can read it from the `CommandRegistry`.

The marker does not change how the command runs from a terminal. Asking for `--force` or a confirmation is still the command's job: database commands use [`DestructiveCommandGuard`](/docs/packages/database/#using-the-policy-in-your-own-commands), and any command can use [`ConfirmationPrompterInterface`](/docs/packages/core/#asking-for-confirmation).

Every shipped command that changes or deletes stored state carries the marker: `db:migrate`, `db:reset`, `db:rollback`, `db:rebuild`, `db:seed`, `cache:clear`, `page-cache:clear`, `page-cache:purge`, `queue:clear`, `log:clear`, `session:gc`, `auth:clear-tokens`, `discovery:clear`, `indexer:rebuild`, `admin-auth:permissions:sync` and `devai:install`. A repository test fails when a shipped command named `*:clear`, `*:reset`, `*:rollback`, `*:rebuild`, `*:fresh`, `*:seed`, `*:truncate`, `*:purge` or `*:clear-tokens`, or one that uses `DestructiveCommandGuard`, is missing it.

### Exceptions

The CLI provides rich error messages with context and suggestions via `CliException` and its subclasses:

- `ProjectNotFoundException` --- thrown when no Marko project is found in the current directory or any parent directory.
- `CommandNotFoundException` --- thrown when the requested command does not exist. Suggests running `marko list`.
- `BootstrapException` --- thrown when the application fails to boot.

All CLI exceptions extend `CliException`, which includes `getContext()` and `getSuggestion()` methods for helpful error output. The context and suggestion of a `MarkoException` thrown by a command (such as the `CommandException` for an undeclared option) are printed the same way.
