<?php

declare(strict_types=1);

use Marko\Queue\Exceptions\NoDriverException;
use Marko\Queue\Exceptions\QueueException;

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
            ->and($exception->getSuggestion())->toContain('https://marko.build/docs/packages/queue-rabbitmq/');
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

    it('extends QueueException', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception)->toBeInstanceOf(QueueException::class);
    });
});
