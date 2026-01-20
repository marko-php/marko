<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

// Test fixtures
#[Command(name: 'test:command')]
class TestCommand {}

#[Command(name: 'test:described', description: 'A test command with description')]
class DescribedCommand {}

it('creates Command attribute with name parameter', function (): void {
    $reflection = new ReflectionClass(TestCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1);

    $command = $attributes[0]->newInstance();

    expect($command->name)->toBe('test:command');
});

it('creates Command attribute with description parameter', function (): void {
    $reflection = new ReflectionClass(DescribedCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1);

    $command = $attributes[0]->newInstance();

    expect($command->name)->toBe('test:described')
        ->and($command->description)->toBe('A test command with description');
});

it('creates Command attribute with optional description defaulting to empty string', function (): void {
    $reflection = new ReflectionClass(TestCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    $command = $attributes[0]->newInstance();

    expect($command->description)->toBe('');
});

it('targets only classes with Command attribute', function (): void {
    $reflection = new ReflectionClass(Command::class);
    $attributes = $reflection->getAttributes(Attribute::class);

    expect($attributes)->toHaveCount(1);

    $attribute = $attributes[0]->newInstance();

    expect($attribute->flags)->toBe(Attribute::TARGET_CLASS);
});

it('marks Command attribute as readonly', function (): void {
    $reflection = new ReflectionClass(Command::class);

    expect($reflection->isReadOnly())->toBeTrue();
});

it('defines CommandInterface with execute method signature', function (): void {
    $reflection = new ReflectionClass(CommandInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->hasMethod('execute'))->toBeTrue();
});

it('requires execute method to accept Input and Output parameters', function (): void {
    $reflection = new ReflectionClass(CommandInterface::class);
    $method = $reflection->getMethod('execute');
    $parameters = $method->getParameters();

    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('input')
        ->and($parameters[0]->getType()->getName())->toBe(Input::class)
        ->and($parameters[1]->getName())->toBe('output')
        ->and($parameters[1]->getType()->getName())->toBe(Output::class);
});

it('requires execute method to return integer exit code', function (): void {
    $reflection = new ReflectionClass(CommandInterface::class);
    $method = $reflection->getMethod('execute');
    $returnType = $method->getReturnType();

    expect($returnType)->not->toBeNull()
        ->and($returnType->getName())->toBe('int');
});
