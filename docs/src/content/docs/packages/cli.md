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
marko list              # Show all available commands
marko module:list       # List installed modules
marko cache:clear       # Clear cache (if cache module installed)
marko db:migrate        # Run migrations (if database module installed)
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
    public function getCommand(): ?string;
    public function getArgument(int $index): ?string;
    public function getArguments(): array;
    public function getOption(string $name): ?string;
    public function hasOption(string $name): bool;
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

#[Command(name: 'namespace:name', description: 'What it does')]
```

### Exceptions

The CLI provides rich error messages with context and suggestions via `CliException` and its subclasses:

- `ProjectNotFoundException` --- thrown when no Marko project is found in the current directory or any parent directory.
- `CommandNotFoundException` --- thrown when the requested command does not exist. Suggests running `marko list`.
- `BootstrapException` --- thrown when the application fails to boot.

All CLI exceptions extend `CliException`, which includes `getContext()` and `getSuggestion()` methods for helpful error output.
