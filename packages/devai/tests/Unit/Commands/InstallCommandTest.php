<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\DevAi\Commands\InstallCommand;

it('supports non-interactive mode via the --agents flag', function (): void {
    $input = new Input(['marko', 'devai:install', '--agents=claude-code']);
    expect($input->getOption('agents'))->toBe('claude-code');
});

it('is registered via Command attribute with name devai:install', function (): void {
    $reflection = new ReflectionClass(InstallCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();

    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('devai:install');
});
