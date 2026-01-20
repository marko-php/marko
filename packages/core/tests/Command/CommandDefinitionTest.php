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
