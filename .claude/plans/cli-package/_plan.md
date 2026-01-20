# Plan: CLI Package

## Created
2025-01-20

## Status
in_progress

## Objective
Create the marko/cli package - a global thin-client CLI tool that discovers and executes commands from modules, plus add command infrastructure to marko/core.

## Scope

### In Scope
- `#[Command]` attribute for command discovery in marko/core
- `CommandInterface` defining command contract
- Command discovery, registry, and execution infrastructure
- Input/Output classes for command I/O
- Built-in commands: `list`, `module:list`
- Application integration (discover commands during boot)
- Thin `marko/cli` package for global installation
- Project finder (locates project root)
- bin/marko executable

### Out of Scope
- Commands from other packages (route:list, db:migrate, etc.)
- Interactive prompts or advanced terminal UI
- Command argument/option parsing beyond basic support
- Async command execution
- Command scheduling/cron

## Success Criteria
- [ ] Commands can be defined using `#[Command]` attribute
- [ ] Commands are automatically discovered from modules
- [ ] `marko list` shows all available commands
- [ ] `marko module:list` shows all modules and their status
- [ ] Global `marko` command finds and boots projects correctly
- [ ] Helpful errors when run outside a Marko project
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Command Attribute and Interface | - | completed |
| 002 | CommandDefinition value object | 001 | completed |
| 003 | Input and Output classes | - | completed |
| 004 | CommandDiscovery | 001, 002 | completed |
| 005 | CommandRegistry | 002 | completed |
| 006 | CommandRunner | 003, 005 | pending |
| 007 | Core Application Integration | 004, 005, 006 | pending |
| 008 | ListCommand | 001, 003, 005 | pending |
| 009 | ModuleListCommand | 001, 003 | completed |
| 010 | CLI Package Foundation | - | completed |
| 011 | ProjectFinder | 010 | completed |
| 012 | CLI Exceptions | 010 | completed |
| 013 | CliKernel | 011, 012 | completed |
| 014 | bin/marko executable | 013 | pending |

## Architecture Notes

### Package Split
- **marko/core**: Command infrastructure (#[Command], discovery, registry, runner, built-in commands)
- **marko/cli**: Thin client (finds project, boots it, hands off to command runner)

### Command Structure
Following the Observer pattern:
```php
#[Command('module:list', 'Show all modules and their status')]
class ModuleListCommand implements CommandInterface
{
    public function execute(Input $input, Output $output): int
    {
        // Command logic here
        return 0; // Exit code
    }
}
```

### Thin Client Architecture
The global `marko` command:
1. Finds project root (looks for `vendor/marko/core`)
2. Requires Composer autoloader
3. Boots the Application
4. Delegates to the project's command runner

### Directory Structure
```
packages/cli/
  bin/
    marko           # Executable
  src/
    CliKernel.php
    ProjectFinder.php
    Exceptions/
      ProjectNotFoundException.php
  composer.json

packages/core/src/
  Attributes/
    Command.php     # NEW
  Command/
    CommandInterface.php
    CommandDefinition.php
    CommandDiscovery.php
    CommandRegistry.php
    CommandRunner.php
    Input.php
    Output.php
  Commands/         # Built-in commands
    ListCommand.php
    ModuleListCommand.php
```

## Risks & Mitigations
- **Cross-platform compatibility**: Use PHP's built-in functions for path handling, avoid shell-specific features
- **Global vs local PHP versions**: Document PHP version requirements clearly
- **Autoloader conflicts**: Boot project's autoloader cleanly, don't pollute global namespace
