<?php

declare(strict_types=1);

use Marko\Core\Command\CommandDefinition;
use Marko\Core\Command\CommandRegistry;
use Marko\Core\Exceptions\CommandException;

it('registers a CommandDefinition', function (): void {
    $registry = new CommandRegistry();
    $definition = new CommandDefinition(
        commandClass: 'App\Command\TestCommand',
        name: 'test:command',
        description: 'A test command',
    );

    $registry->register($definition);

    expect($registry->get('test:command'))->toBe($definition);
});

it('retrieves command by name', function (): void {
    $registry = new CommandRegistry();
    $definition1 = new CommandDefinition(
        commandClass: 'App\Command\FirstCommand',
        name: 'first:command',
        description: 'First command',
    );
    $definition2 = new CommandDefinition(
        commandClass: 'App\Command\SecondCommand',
        name: 'second:command',
        description: 'Second command',
    );

    $registry->register($definition1);
    $registry->register($definition2);

    expect($registry->get('second:command'))->toBe($definition2)
        ->and($registry->get('first:command'))->toBe($definition1);
});

it('returns null for unknown command name', function (): void {
    $registry = new CommandRegistry();

    expect($registry->get('unknown:command'))->toBeNull();
});

it('returns all registered commands', function (): void {
    $registry = new CommandRegistry();
    $definition1 = new CommandDefinition(
        commandClass: 'App\Command\FirstCommand',
        name: 'first:command',
        description: 'First command',
    );
    $definition2 = new CommandDefinition(
        commandClass: 'App\Command\SecondCommand',
        name: 'second:command',
        description: 'Second command',
    );

    $registry->register($definition1);
    $registry->register($definition2);

    $all = $registry->all();

    expect($all)->toHaveCount(2)
        ->and($all)->toContain($definition1)
        ->and($all)->toContain($definition2);
});

it('checks if command exists by name', function (): void {
    $registry = new CommandRegistry();
    $definition = new CommandDefinition(
        commandClass: 'App\Command\TestCommand',
        name: 'test:command',
        description: 'A test command',
    );

    $registry->register($definition);

    expect($registry->has('test:command'))->toBeTrue()
        ->and($registry->has('unknown:command'))->toBeFalse();
});

it('throws CommandException on duplicate command name registration', function (): void {
    $registry = new CommandRegistry();
    $definition1 = new CommandDefinition(
        commandClass: 'App\Command\FirstCommand',
        name: 'test:command',
        description: 'First command',
    );
    $definition2 = new CommandDefinition(
        commandClass: 'App\Command\SecondCommand',
        name: 'test:command',
        description: 'Second command with same name',
    );

    $registry->register($definition1);

    expect(fn () => $registry->register($definition2))->toThrow(
        CommandException::class,
        "Command 'test:command' is already registered",
    );
});

it('resolves command by alias', function (): void {
    $registry = new CommandRegistry();
    $definition = new CommandDefinition(
        commandClass: 'App\Command\TestCommand',
        name: 'test:command',
        description: 'A test command',
        aliases: ['tc', 'test'],
    );

    $registry->register($definition);

    expect($registry->get('tc'))->toBe($definition)
        ->and($registry->get('test'))->toBe($definition);
});

it('reports alias exists via has method', function (): void {
    $registry = new CommandRegistry();
    $definition = new CommandDefinition(
        commandClass: 'App\Command\TestCommand',
        name: 'test:command',
        description: 'A test command',
        aliases: ['tc'],
    );

    $registry->register($definition);

    expect($registry->has('tc'))->toBeTrue()
        ->and($registry->has('unknown'))->toBeFalse();
});

it('throws CommandException when alias conflicts with existing command name', function (): void {
    $registry = new CommandRegistry();
    $existing = new CommandDefinition(
        commandClass: 'App\Command\ExistingCommand',
        name: 'test:command',
        description: 'Existing command',
    );
    $conflicting = new CommandDefinition(
        commandClass: 'App\Command\ConflictingCommand',
        name: 'other:command',
        description: 'Command with conflicting alias',
        aliases: ['test:command'],
    );

    $registry->register($existing);

    expect(fn () => $registry->register($conflicting))->toThrow(
        CommandException::class,
        "Command 'test:command' is already registered",
    );
});

it('throws CommandException when alias conflicts with another alias', function (): void {
    $registry = new CommandRegistry();
    $first = new CommandDefinition(
        commandClass: 'App\Command\FirstCommand',
        name: 'first:command',
        description: 'First command',
        aliases: ['shared-alias'],
    );
    $second = new CommandDefinition(
        commandClass: 'App\Command\SecondCommand',
        name: 'second:command',
        description: 'Second command with conflicting alias',
        aliases: ['shared-alias'],
    );

    $registry->register($first);

    expect(fn () => $registry->register($second))->toThrow(
        CommandException::class,
        "Command 'shared-alias' is already registered",
    );
});

it('does not duplicate aliased commands in all method', function (): void {
    $registry = new CommandRegistry();
    $definition = new CommandDefinition(
        commandClass: 'App\Command\TestCommand',
        name: 'test:command',
        description: 'A test command',
        aliases: ['tc', 'test'],
    );

    $registry->register($definition);

    $all = $registry->all();

    expect($all)->toHaveCount(1)
        ->and($all[0])->toBe($definition);
});

it('registers command with multiple aliases', function (): void {
    $registry = new CommandRegistry();
    $definition = new CommandDefinition(
        commandClass: 'App\Command\TestCommand',
        name: 'test:command',
        description: 'A test command',
        aliases: ['tc', 'test', 't:cmd'],
    );

    $registry->register($definition);

    expect($registry->get('tc'))->toBe($definition)
        ->and($registry->get('test'))->toBe($definition)
        ->and($registry->get('t:cmd'))->toBe($definition)
        ->and($registry->get('test:command'))->toBe($definition);
});

it('returns commands sorted alphabetically by name', function (): void {
    $registry = new CommandRegistry();
    $zebra = new CommandDefinition(
        commandClass: 'App\Command\ZebraCommand',
        name: 'zebra:command',
        description: 'Zebra command',
    );
    $apple = new CommandDefinition(
        commandClass: 'App\Command\AppleCommand',
        name: 'apple:command',
        description: 'Apple command',
    );
    $mango = new CommandDefinition(
        commandClass: 'App\Command\MangoCommand',
        name: 'mango:command',
        description: 'Mango command',
    );

    // Register in non-alphabetical order
    $registry->register($zebra);
    $registry->register($apple);
    $registry->register($mango);

    $all = $registry->all();

    expect($all[0])->toBe($apple)
        ->and($all[1])->toBe($mango)
        ->and($all[2])->toBe($zebra);
});
