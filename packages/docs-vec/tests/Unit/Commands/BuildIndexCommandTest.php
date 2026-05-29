<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\DocsVec\Commands\BuildIndexCommand;

it('registers a #[Command(name: \'docs-vec:build\')] CLI command', function (): void {
    $reflection = new ReflectionClass(BuildIndexCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->not->toBeEmpty();

    $attr = $attributes[0]->newInstance();

    expect($attr->name)->toBe('docs-vec:build')
        ->and($attr->description)->toContain('hybrid');
});

it('implements CommandInterface', function (): void {
    expect(BuildIndexCommand::class)->toImplement(CommandInterface::class);
});
