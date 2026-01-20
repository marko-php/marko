<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandDefinition;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\CommandRegistry;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Commands\ListCommand;

it('has Command attribute with name list', function (): void {
    $reflection = new ReflectionClass(ListCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1);

    $command = $attributes[0]->newInstance();

    expect($command->name)->toBe('list');
});

it('has Command attribute with description Show all available commands', function (): void {
    $reflection = new ReflectionClass(ListCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    $command = $attributes[0]->newInstance();

    expect($command->description)->toBe('Show all available commands');
});

it('implements CommandInterface', function (): void {
    $reflection = new ReflectionClass(ListCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();
});

it('outputs all registered commands', function (): void {
    $registry = new CommandRegistry();
    $registry->register(new CommandDefinition(
        commandClass: 'App\Commands\FooCommand',
        name: 'foo',
        description: 'Foo command',
    ));
    $registry->register(new CommandDefinition(
        commandClass: 'App\Commands\BarCommand',
        name: 'bar',
        description: 'Bar command',
    ));

    $command = new ListCommand($registry);

    $stream = fopen('php://memory', 'r+');
    $input = new Input([]);
    $output = new Output($stream);

    $command->execute($input, $output);

    rewind($stream);
    $result = stream_get_contents($stream);

    expect($result)->toContain('foo')
        ->and($result)->toContain('bar');
});

it('displays command name and description for each command', function (): void {
    $registry = new CommandRegistry();
    $registry->register(new CommandDefinition(
        commandClass: 'App\Commands\ListCommand',
        name: 'list',
        description: 'Show all available commands',
    ));
    $registry->register(new CommandDefinition(
        commandClass: 'App\Commands\ModuleListCommand',
        name: 'module:list',
        description: 'Show all modules and their status',
    ));

    $command = new ListCommand($registry);

    $stream = fopen('php://memory', 'r+');
    $input = new Input([]);
    $output = new Output($stream);

    $command->execute($input, $output);

    rewind($stream);
    $result = stream_get_contents($stream);

    expect($result)->toContain('list')
        ->and($result)->toContain('Show all available commands')
        ->and($result)->toContain('module:list')
        ->and($result)->toContain('Show all modules and their status');
});

it('returns exit code 0 on success', function (): void {
    $registry = new CommandRegistry();
    $registry->register(new CommandDefinition(
        commandClass: 'App\Commands\FooCommand',
        name: 'foo',
        description: 'Foo command',
    ));

    $command = new ListCommand($registry);

    $stream = fopen('php://memory', 'r+');
    $input = new Input([]);
    $output = new Output($stream);

    $exitCode = $command->execute($input, $output);

    expect($exitCode)->toBe(0);
});

it('formats output with aligned columns', function (): void {
    $registry = new CommandRegistry();
    $registry->register(new CommandDefinition(
        commandClass: 'App\Commands\ListCommand',
        name: 'list',
        description: 'Show all available commands',
    ));
    $registry->register(new CommandDefinition(
        commandClass: 'App\Commands\ModuleListCommand',
        name: 'module:list',
        description: 'Show all modules and their status',
    ));

    $command = new ListCommand($registry);

    $stream = fopen('php://memory', 'r+');
    $input = new Input([]);
    $output = new Output($stream);

    $command->execute($input, $output);

    rewind($stream);
    $result = stream_get_contents($stream);

    $lines = array_values(array_filter(explode("\n", $result), fn ($line) => trim($line) !== ''));

    // Find lines that contain commands (have descriptions)
    $listLine = '';
    $moduleLine = '';
    foreach ($lines as $line) {
        if (str_contains($line, 'Show all available commands')) {
            $listLine = $line;
        }
        if (str_contains($line, 'Show all modules and their status')) {
            $moduleLine = $line;
        }
    }

    // Check that descriptions start at the same column position
    $listDescPos = strpos($listLine, 'Show all available commands');
    $moduleDescPos = strpos($moduleLine, 'Show all modules and their status');

    expect($listDescPos)->toBe($moduleDescPos)
        ->and($listDescPos)->toBeGreaterThan(strlen('module:list'));
});

it('shows message when no commands available', function (): void {
    $registry = new CommandRegistry();

    $command = new ListCommand($registry);

    $stream = fopen('php://memory', 'r+');
    $input = new Input([]);
    $output = new Output($stream);

    $command->execute($input, $output);

    rewind($stream);
    $result = stream_get_contents($stream);

    expect($result)->toContain('No commands available');
});
