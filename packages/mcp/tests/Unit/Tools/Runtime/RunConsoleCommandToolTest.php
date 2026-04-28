<?php

declare(strict_types=1);

use Marko\Mcp\Tools\Runtime\Contracts\ConsoleDispatcherInterface;
use Marko\Mcp\Tools\Runtime\RunConsoleCommandTool;

function makeDispatcher(array $result): ConsoleDispatcherInterface
{
    return new class ($result) implements ConsoleDispatcherInterface
    {
        public function __construct(private readonly array $result) {}

        public function dispatch(string $command, array $args = []): array
        {
            return $this->result;
        }
    };
}

it('registers run_console_command tool delegating to the CLI dispatcher', function (): void {
    $dispatcher = makeDispatcher([
        'exitCode' => 0,
        'stdout' => 'Hello, World!',
        'stderr' => '',
    ]);

    $definition = RunConsoleCommandTool::definition($dispatcher);

    expect($definition->name)->toBe('run_console_command');

    $result = $definition->handler->handle(['command' => 'greet', 'args' => ['--name=World']]);
    $text = $result['content'][0]['text'];

    expect($text)->toContain('exitCode: 0')
        ->and($text)->toContain('Hello, World!');
});
