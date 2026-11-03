<?php

declare(strict_types=1);

use Marko\Database\Command\DestructiveCommandGuard;
use Marko\Tests\Support\ShippedCommands\ShippedCommands;

require_once __DIR__ . '/Support/ShippedCommands/ShippedCommands.php';

/**
 * Every shipped command that changes or deletes stored state must say so with #[Command(destructive: true)], so the
 * MCP run_console_command tool (and any other caller that runs commands on someone else's behalf) can refuse it
 * without guessing from option names.
 */
const DESTRUCTIVE_COMMAND_SUFFIXES = [
    ':clear',
    ':reset',
    ':rollback',
    ':rebuild',
    ':fresh',
    ':seed',
    ':truncate',
    ':purge',
    ':clear-tokens',
];

it('finds the shipped commands', function (): void {
    expect(ShippedCommands::all())->toHaveKeys(['cache:clear', 'db:reset', 'list', 'queue:clear']);
});

it('marks every command named like a state-wiping command as destructive', function (): void {
    $unmarked = [];

    foreach (ShippedCommands::all() as $name => $command) {
        $wipesState = array_any(
            DESTRUCTIVE_COMMAND_SUFFIXES,
            fn (string $suffix): bool => str_ends_with($name, $suffix),
        );

        if ($wipesState && !$command['attribute']->destructive) {
            $unmarked[] = "$name ({$command['class']})";
        }
    }

    expect($unmarked)->toBe([], 'Add destructive: true to the #[Command] attribute of: ' . implode(', ', $unmarked));
});

it('marks every command guarded by DestructiveCommandGuard as destructive', function (): void {
    $unmarked = [];

    foreach (ShippedCommands::all() as $name => $command) {
        $constructor = new ReflectionClass($command['class'])->getConstructor();
        $guarded = array_any(
            $constructor?->getParameters() ?? [],
            fn (ReflectionParameter $parameter): bool => $parameter->getType() instanceof ReflectionNamedType
                && $parameter->getType()->getName() === DestructiveCommandGuard::class,
        );

        if ($guarded && !$command['attribute']->destructive) {
            $unmarked[] = "$name ({$command['class']})";
        }
    }

    expect($unmarked)->toBe([], 'Add destructive: true to the #[Command] attribute of: ' . implode(', ', $unmarked));
});

it('keeps read-only commands unmarked', function (): void {
    $commands = ShippedCommands::all();

    foreach (['list', 'module:list', 'cache:status', 'db:status', 'queue:status', 'route:list'] as $name) {
        expect($commands[$name]['attribute']->destructive)->toBeFalse();
    }
});
