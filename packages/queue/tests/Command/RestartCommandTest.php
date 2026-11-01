<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Queue\Command\RestartCommand;
use Marko\Queue\Tests\Command\Helpers;
use Marko\Queue\Tests\Fixtures\InMemoryRestartSignal;

describe('queue:restart', function (): void {
    it('registers as queue:restart command via #[Command] attribute', function (): void {
        $attributes = new ReflectionClass(RestartCommand::class)->getAttributes(Command::class);

        expect($attributes)->toHaveCount(1)
            ->and($attributes[0]->newInstance()->name)->toBe('queue:restart');
    });

    it('implements CommandInterface', function (): void {
        expect(new ReflectionClass(RestartCommand::class)->implementsInterface(CommandInterface::class))
            ->toBeTrue();
    });

    it('requests a restart and tells the user workers exit after their current job', function (): void {
        $signal = new InMemoryRestartSignal();
        ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();

        $exitCode = new RestartCommand($signal)->execute(new Input(['marko', 'queue:restart']), $output);

        expect($exitCode)->toBe(0)
            ->and($signal->requests)->toBe(1)
            ->and(Helpers::getOutputContent($stream))
            ->toContain('Restart signal sent; workers will exit after their current job.');
    });
});
