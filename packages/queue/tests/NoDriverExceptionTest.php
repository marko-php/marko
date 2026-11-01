<?php

declare(strict_types=1);

use Marko\Queue\Exceptions\NoDriverException;
use Marko\Queue\Exceptions\QueueException;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\QueueInterface;
use Marko\Queue\WorkerInterface;

describe('NoDriverException', function (): void {
    it('loads the driver list from known-drivers.php', function (): void {
        $knownDrivers = require __DIR__ . '/../known-drivers.php';
        $exception = NoDriverException::noDriverInstalled();

        foreach (array_keys($knownDrivers) as $package) {
            expect($exception->getSuggestion())->toContain($package);
        }
    });

    it('includes the description for each driver in the suggestion', function (): void {
        $knownDrivers = require __DIR__ . '/../known-drivers.php';
        $exception = NoDriverException::noDriverInstalled();

        foreach ($knownDrivers as $package => $description) {
            expect($exception->getSuggestion())->toContain($description);
        }
    });

    it('includes a composer require command for each driver', function (): void {
        $knownDrivers = require __DIR__ . '/../known-drivers.php';
        $exception = NoDriverException::noDriverInstalled();

        foreach (array_keys($knownDrivers) as $package) {
            expect($exception->getSuggestion())->toContain("composer require $package");
        }
    });

    it('includes a derived docs URL for each driver', function (): void {
        $knownDrivers = require __DIR__ . '/../known-drivers.php';
        $exception = NoDriverException::noDriverInstalled();

        foreach (array_keys($knownDrivers) as $package) {
            $basename = substr($package, strlen('marko/'));
            expect($exception->getSuggestion())->toContain("https://marko.build/docs/packages/$basename/");
        }
    });

    it('queue NoDriverException reads from known-drivers.php and includes docs URLs', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())
            ->toContain('https://marko.build/docs/packages/queue-sync/')
            ->and($exception->getSuggestion())->toContain('https://marko.build/docs/packages/queue-database/')
            ->and($exception->getSuggestion())->toContain('https://marko.build/docs/packages/queue-rabbitmq/')
            ->and($exception->getSuggestion())->toContain('https://marko.build/docs/packages/queue-redis/');
    });

    it('lists queue-sync first in the suggestion (matching known-drivers.php order)', function (): void {
        $exception = NoDriverException::noDriverInstalled();
        $suggestion = $exception->getSuggestion();

        $syncPos = strpos($suggestion, 'marko/queue-sync');
        $databasePos = strpos($suggestion, 'marko/queue-database');
        $rabbitmqPos = strpos($suggestion, 'marko/queue-rabbitmq');

        expect($syncPos)->toBeLessThan($databasePos)
            ->and($databasePos)->toBeLessThan($rabbitmqPos);
    });

    it('no longer exposes a DRIVER_PACKAGES const', function (): void {
        $reflection = new ReflectionClass(NoDriverException::class);
        $constant = $reflection->getReflectionConstant('DRIVER_PACKAGES');

        expect($constant)->toBeFalse();
    });

    it('provides suggestion with composer require commands for all driver packages', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())
            ->toContain('composer require marko/queue-sync')
            ->and($exception->getSuggestion())->toContain('composer require marko/queue-database')
            ->and($exception->getSuggestion())->toContain('composer require marko/queue-rabbitmq');
    });

    it('includes context about resolving queue interfaces', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getContext())->toContain('queue interface');
    });

    it('keeps the driver install message when called without an interface', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getMessage())->toBe('No queue driver installed.');
    });

    it('keeps the driver install message for QueueInterface and FailedJobRepositoryInterface', function (): void {
        $queueException = NoDriverException::noDriverInstalled(QueueInterface::class);
        $failedJobException = NoDriverException::noDriverInstalled(FailedJobRepositoryInterface::class);

        expect($queueException->getMessage())->toBe('No queue driver installed.')
            ->and($failedJobException->getMessage())->toBe('No queue driver installed.')
            ->and($queueException->getSuggestion())->toContain('composer require marko/queue-database');
    });

    it('names the unbound interface when it is not a driver contract', function (): void {
        $exception = NoDriverException::noDriverInstalled(WorkerInterface::class);

        expect($exception->getMessage())->toBe('No implementation is bound for ' . WorkerInterface::class . '.')
            ->and($exception->getMessage())->not->toContain('No queue driver installed');
    });

    it('suggests binding the interface in module.php when it is not a driver contract', function (): void {
        $exception = NoDriverException::noDriverInstalled(WorkerInterface::class);

        expect($exception->getSuggestion())
            ->toContain('module.php')
            ->and($exception->getSuggestion())->toContain(WorkerInterface::class);
    });

    it('extends QueueException', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception)->toBeInstanceOf(QueueException::class);
    });
});
