<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\DevAi\Commands\InstallCommand;

it('supports non-interactive mode via flags', function (): void {
    // When --agents and --docs-driver are both provided, no interactive prompt occurs
    $input = new Input(['marko', 'devai:install', '--agents=claude-code', '--docs-driver=fts']);
    expect($input->getOption('agents'))->toBe('claude-code')
        ->and($input->getOption('docs-driver'))->toBe('fts');
});

it('is registered via Command attribute with name devai:install', function (): void {
    $reflection = new ReflectionClass(InstallCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();

    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('devai:install');
});
