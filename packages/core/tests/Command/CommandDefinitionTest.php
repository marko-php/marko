<?php

declare(strict_types=1);

use Marko\Core\Command\CommandDefinition;

it('creates CommandDefinition with command class name', function (): void {
    $definition = new CommandDefinition(
        commandClass: 'App\Command\TestCommand',
        name: 'test:command',
        description: 'A test command',
    );

    expect($definition)->toBeInstanceOf(CommandDefinition::class);
});

it('creates CommandDefinition with command name from attribute', function (): void {
    $definition = new CommandDefinition(
        commandClass: 'App\Command\TestCommand',
        name: 'app:test',
        description: '',
    );

    expect($definition->name)->toBe('app:test');
});

it('creates CommandDefinition with description from attribute', function (): void {
    $definition = new CommandDefinition(
        commandClass: 'App\Command\TestCommand',
        name: 'test:command',
        description: 'A detailed description of the command',
    );

    expect($definition->description)->toBe('A detailed description of the command');
});

it('stores aliases in CommandDefinition', function (): void {
    $definition = new CommandDefinition(
        commandClass: 'App\Command\TestCommand',
        name: 'dev:up',
        aliases: ['up'],
    );

    expect($definition->aliases)->toBe(['up']);
});

it('defaults aliases to empty array in CommandDefinition when not provided', function (): void {
    $definition = new CommandDefinition(
        commandClass: 'App\Command\TestCommand',
        name: 'test:command',
    );

    expect($definition->aliases)->toBeEmpty();
});

it('stores declared flags in CommandDefinition', function (): void {
    $definition = new CommandDefinition(
        commandClass: 'App\Command\TestCommand',
        name: 'queue:retry',
        flags: ['all'],
    );

    expect($definition->flags)->toBe(['all']);
});

it('defaults command flags to an empty list', function (): void {
    $definition = new CommandDefinition(
        commandClass: 'App\Command\TestCommand',
        name: 'test:command',
    );

    expect($definition->flags)->toBe([]);
});

it('stores declared value-taking options in CommandDefinition', function (): void {
    $definition = new CommandDefinition(
        commandClass: 'App\Command\TestCommand',
        name: 'db:seed',
        options: ['class'],
    );

    expect($definition->options)->toBe(['class']);
});

it('accepts its options, its flags and the global flags', function (): void {
    $definition = new CommandDefinition(
        commandClass: 'App\Command\TestCommand',
        name: 'db:seed',
        flags: ['force'],
        options: ['class', 'force'],
    );

    expect($definition->acceptedOptions())->toBe(['class', 'force', 'no-interaction', 'help']);
});

it('shows each accepted option the way it is typed', function (): void {
    $definition = new CommandDefinition(
        commandClass: 'App\Command\TestCommand',
        name: 'dev:up',
        flags: ['d'],
        options: ['port', 'p'],
    );

    expect($definition->acceptedOptionUsages())
        ->toBe(['--port=<value>', '-p=<value>', '-d', '--no-interaction', '--help']);
});

it('stores the destructive marker in CommandDefinition', function (): void {
    $definition = new CommandDefinition(
        commandClass: 'App\Command\TestCommand',
        name: 'cache:clear',
        destructive: true,
    );

    expect($definition->destructive)->toBeTrue();
});

it('defaults the destructive marker to false', function (): void {
    $definition = new CommandDefinition(
        commandClass: 'App\Command\TestCommand',
        name: 'test:command',
    );

    expect($definition->destructive)->toBeFalse();
});

it('marks CommandDefinition as readonly', function (): void {
    $reflection = new ReflectionClass(CommandDefinition::class);

    expect($reflection->isReadOnly())->toBeTrue();
});

it('exposes commandClass property', function (): void {
    $definition = new CommandDefinition(
        commandClass: 'App\Command\MyCommand',
        name: 'my:command',
        description: 'My command description',
    );

    expect($definition->commandClass)->toBe('App\Command\MyCommand');
});

it('exposes name property', function (): void {
    $definition = new CommandDefinition(
        commandClass: 'App\Command\TestCommand',
        name: 'cache:clear',
        description: 'Clears the cache',
    );

    expect($definition->name)->toBe('cache:clear');
});

it('exposes description property', function (): void {
    $definition = new CommandDefinition(
        commandClass: 'App\Command\TestCommand',
        name: 'test:command',
        description: 'This is the description',
    );

    expect($definition->description)->toBe('This is the description');
});
