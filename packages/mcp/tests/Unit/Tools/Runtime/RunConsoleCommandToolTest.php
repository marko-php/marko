<?php

declare(strict_types=1);

use Marko\Core\Command\CommandDefinition;
use Marko\Mcp\Tools\Runtime\Adapters\MarkoConsoleDispatcher;
use Marko\Mcp\Tools\Runtime\RunConsoleCommandTool;

/**
 * A dispatcher that knows the given command definitions and records every command it dispatches.
 *
 * @param list<CommandDefinition> $definitions
 */
function makeDispatcher(
    array $result,
    array $definitions = [],
): MarkoConsoleDispatcher {
    return new readonly class ($result, $definitions, new ArrayObject()) extends MarkoConsoleDispatcher
    {
        /**
         * @param list<CommandDefinition> $definitions
         */
        public function __construct(
            private array $result,
            private array $definitions,
            public ArrayObject $dispatched,
        ) {}

        public function find(
            string $command,
        ): ?CommandDefinition {
            foreach ($this->definitions as $definition) {
                if (
                    $definition->name === $command
                    || in_array($command, $definition->aliases, true)
                ) {
                    return $definition;
                }
            }

            return null;
        }

        public function dispatch(
            string $command,
            array $args = [],
        ): array {
            $this->dispatched->append($command);

            return $this->result;
        }
    };
}

function okResult(): array
{
    return ['exitCode' => 0, 'stdout' => 'done', 'stderr' => ''];
}

it('registers run_console_command tool delegating to the CLI dispatcher', function (): void {
    $dispatcher = makeDispatcher([
        'exitCode' => 0,
        'stdout' => 'Hello, World!',
        'stderr' => '',
    ], [new CommandDefinition('GreetCommand', 'greet')]);

    $definition = RunConsoleCommandTool::definition($dispatcher, allowedCommands: ['greet']);

    expect($definition->name)->toBe('run_console_command');

    $result = $definition->handler->handle(['command' => 'greet', 'args' => ['--name=World']]);
    $text = $result['content'][0]['text'];

    expect($text)->toContain('exitCode: 0')
        ->and($text)->toContain('Hello, World!');
});

it('refuses a command that is not in the allowlist without dispatching it', function (): void {
    $dispatcher = makeDispatcher(okResult(), [
        new CommandDefinition('CacheClearCommand', 'cache:clear'),
    ]);

    $result = RunConsoleCommandTool::definition($dispatcher, allowedCommands: ['route:list'])
        ->handler->handle(['command' => 'cache:clear']);

    expect($result['isError'] ?? false)->toBeTrue()
        ->and($result['content'][0]['text'])->toContain("Command 'cache:clear' is not allowed")
        ->and($result['content'][0]['text'])->toContain('mcp.console.allowed_commands')
        ->and($dispatcher->dispatched->getArrayCopy())->toBe([]);
});

it('refuses every command when the allowlist is empty', function (): void {
    $dispatcher = makeDispatcher(okResult(), [new CommandDefinition('RouteListCommand', 'route:list')]);

    $result = RunConsoleCommandTool::definition($dispatcher)->handler->handle(['command' => 'route:list']);

    expect($result['isError'] ?? false)->toBeTrue()
        ->and($result['content'][0]['text'])->toContain('Allowed: (none)')
        ->and($dispatcher->dispatched->getArrayCopy())->toBe([]);
});

it('runs an alias of an allowlisted command', function (): void {
    $dispatcher = makeDispatcher(okResult(), [
        new CommandDefinition('DevStatusCommand', 'dev:status', aliases: ['status']),
    ]);

    $result = RunConsoleCommandTool::definition($dispatcher, allowedCommands: ['dev:status'])
        ->handler->handle(['command' => 'status']);

    expect($result['isError'] ?? false)->toBeFalse()
        ->and($dispatcher->dispatched->getArrayCopy())->toBe(['status']);
});

it('refuses an allowlisted destructive command even when the agent passes --force', function (): void {
    $dispatcher = makeDispatcher(okResult(), [
        new CommandDefinition('ResetCommand', 'db:reset', flags: ['force'], destructive: true),
    ]);

    $result = RunConsoleCommandTool::definition($dispatcher, allowedCommands: ['db:reset'])
        ->handler->handle(['command' => 'db:reset', 'args' => ['--force']]);

    expect($result['isError'] ?? false)->toBeTrue()
        ->and($result['content'][0]['text'])->toContain('destructive')
        ->and($result['content'][0]['text'])->toContain('mcp.console.allow_destructive')
        ->and($dispatcher->dispatched->getArrayCopy())->toBe([]);
});

it('refuses an allowlisted destructive command that has no force flag', function (): void {
    $dispatcher = makeDispatcher(okResult(), [
        new CommandDefinition('CacheClearCommand', 'cache:clear', destructive: true),
    ]);

    $result = RunConsoleCommandTool::definition($dispatcher, allowedCommands: ['cache:clear'])
        ->handler->handle(['command' => 'cache:clear']);

    expect($result['isError'] ?? false)->toBeTrue()
        ->and($result['content'][0]['text'])->toContain("Command 'cache:clear' is destructive")
        ->and($result['content'][0]['text'])->toContain('mcp.console.allow_destructive')
        ->and($dispatcher->dispatched->getArrayCopy())->toBe([]);
});

it('refuses a destructive command reached through an allowlisted alias', function (): void {
    $dispatcher = makeDispatcher(okResult(), [
        new CommandDefinition('CacheClearCommand', 'cache:clear', aliases: ['cc'], destructive: true),
    ]);

    $result = RunConsoleCommandTool::definition($dispatcher, allowedCommands: ['cc'])
        ->handler->handle(['command' => 'cc']);

    expect($result['isError'] ?? false)->toBeTrue()
        ->and($result['content'][0]['text'])->toContain('destructive')
        ->and($dispatcher->dispatched->getArrayCopy())->toBe([]);
});

it('runs an allowlisted command that declares a force flag but is not marked destructive', function (): void {
    $dispatcher = makeDispatcher(okResult(), [
        new CommandDefinition('ScaffoldCommand', 'app:scaffold', flags: ['force']),
    ]);

    $result = RunConsoleCommandTool::definition($dispatcher, allowedCommands: ['app:scaffold'])
        ->handler->handle(['command' => 'app:scaffold', 'args' => ['--force']]);

    expect($result['isError'] ?? false)->toBeFalse()
        ->and($dispatcher->dispatched->getArrayCopy())->toBe(['app:scaffold']);
});

it('runs an allowlisted destructive command when allow_destructive is enabled', function (): void {
    $dispatcher = makeDispatcher(okResult(), [
        new CommandDefinition('ResetCommand', 'db:reset', flags: ['force'], destructive: true),
    ]);

    $result = RunConsoleCommandTool::definition(
        $dispatcher,
        allowedCommands: ['db:reset'],
        allowDestructive: true,
    )->handler->handle(['command' => 'db:reset', 'args' => ['--force']]);

    expect($result['isError'] ?? false)->toBeFalse()
        ->and($dispatcher->dispatched->getArrayCopy())->toBe(['db:reset']);
});

it('does not run a destructive command just because allow_destructive is enabled', function (): void {
    $dispatcher = makeDispatcher(okResult(), [
        new CommandDefinition('ResetCommand', 'db:reset', flags: ['force'], destructive: true),
    ]);

    $result = RunConsoleCommandTool::definition(
        $dispatcher,
        allowedCommands: ['route:list'],
        allowDestructive: true,
    )->handler->handle(['command' => 'db:reset', 'args' => ['--force']]);

    expect($result['isError'] ?? false)->toBeTrue()
        ->and($dispatcher->dispatched->getArrayCopy())->toBe([]);
});

it('lists the allowed commands in the tool description', function (): void {
    $definition = RunConsoleCommandTool::definition(
        makeDispatcher(okResult()),
        allowedCommands: ['route:list', 'module:list'],
    );

    expect($definition->description)->toContain('route:list, module:list');
});
