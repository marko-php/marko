<?php

declare(strict_types=1);

use Marko\Queue\Exceptions\NoDriverException;
use Marko\Queue\Exceptions\QueueException;

describe('NoDriverException', function (): void {
    it('has DRIVER_PACKAGES constant listing marko/queue-database, marko/queue-rabbitmq, and marko/queue-sync', function (): void {
        $reflection = new ReflectionClass(NoDriverException::class);
        $constant = $reflection->getReflectionConstant('DRIVER_PACKAGES');

        expect($constant)->not->toBeFalse()
            ->and($constant->getValue())->toContain('marko/queue-database')
            ->and($constant->getValue())->toContain('marko/queue-rabbitmq')
            ->and($constant->getValue())->toContain('marko/queue-sync');
    });

    it('provides suggestion with composer require commands for all driver packages', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())
            ->toContain('composer require marko/queue-database')
            ->and($exception->getSuggestion())->toContain('composer require marko/queue-rabbitmq')
            ->and($exception->getSuggestion())->toContain('composer require marko/queue-sync');
    });

    it('includes context about resolving queue interfaces', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getContext())->toContain('queue interface');
    });

    it('extends QueueException', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception)->toBeInstanceOf(QueueException::class);
    });
});
