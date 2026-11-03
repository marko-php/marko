<?php

declare(strict_types=1);

use Marko\Core\Command\CommandDefinition;
use Marko\Core\Command\Input;
use Marko\Database\Command\DestructiveCommandGuard;
use Marko\Tests\Support\ShippedCommands\ShippedCommands;

require_once __DIR__ . '/Support/ShippedCommands/ShippedCommands.php';

/**
 * The command runner refuses any option a command does not declare, so every option a shipped command reads must
 * be declared in its #[Command] attribute (`options` or `flags`), or the command can no longer be given it.
 */

/**
 * The option names a command class reads through Input: string literals and its own class constants.
 *
 * @param class-string $class
 * @return list<string>
 */
function optionNamesReadBy(
    string $class,
    string $path,
): array {
    $source = (string) file_get_contents($path);
    preg_match_all(
        '/->(?:hasOption|getOption|getOptionValues)\(\s*(?:\'([^\']+)\'|self::(\w+))\s*\)/',
        $source,
        $matches,
        PREG_SET_ORDER,
    );

    $constants = new ReflectionClass($class)->getConstants();
    $names = array_map(
        fn (array $match): string => $match[1] !== '' ? $match[1] : (string) $constants[$match[2]],
        $matches,
    );

    return array_values(array_unique($names));
}

/**
 * @param array{class: class-string, attribute: \Marko\Core\Attributes\Command, path: string} $command
 */
function acceptedOptionsOf(
    array $command,
): CommandDefinition {
    return new CommandDefinition(
        commandClass: $command['class'],
        name: $command['attribute']->name,
        flags: $command['attribute']->flags,
        options: $command['attribute']->options,
    );
}

it('finds option reads in shipped commands', function (): void {
    $commands = ShippedCommands::all();
    $workCommand = $commands['queue:work'];

    expect(optionNamesReadBy($workCommand['class'], $workCommand['path']))->toContain('queue', 'sleep', 'once');
});

it('declares every option a shipped command reads', function (): void {
    $undeclared = [];

    foreach (ShippedCommands::all() as $name => $command) {
        $accepted = acceptedOptionsOf($command)->acceptedOptions();

        foreach (optionNamesReadBy($command['class'], $command['path']) as $option) {
            if (!in_array($option, $accepted, true)) {
                $undeclared[] = "$name reads '$option'";
            }
        }
    }

    expect($undeclared)->toBe(
        [],
        'Declare these in #[Command(options: [...])], or flags: [...] when they take no value: '
        . implode(', ', $undeclared),
    );
});

it('accepts --force on every command guarded by DestructiveCommandGuard', function (): void {
    $missing = [];

    foreach (ShippedCommands::all() as $name => $command) {
        $constructor = new ReflectionClass($command['class'])->getConstructor();
        $guarded = array_any(
            $constructor?->getParameters() ?? [],
            fn (ReflectionParameter $parameter): bool => $parameter->getType() instanceof ReflectionNamedType
                && $parameter->getType()->getName() === DestructiveCommandGuard::class,
        );

        if ($guarded && !in_array('force', $command['attribute']->flags, true)) {
            $missing[] = $name;
        }
    }

    expect($missing)->toBe([], "Add 'force' to the flags of: " . implode(', ', $missing));
});

it('declares no shipped option that collides with a global flag', function (): void {
    $collisions = [];

    foreach (ShippedCommands::all() as $name => $command) {
        foreach (array_intersect($command['attribute']->options, Input::GLOBAL_FLAGS) as $option) {
            $collisions[] = "$name declares '$option'";
        }
    }

    expect($collisions)->toBe([]);
});
