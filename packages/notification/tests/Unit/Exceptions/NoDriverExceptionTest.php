<?php

declare(strict_types=1);

use Marko\Notification\Exceptions\NoDriverException;
use Marko\Notification\Exceptions\NotificationException;

describe('NoDriverException', function (): void {
    it('has DRIVER_PACKAGES constant listing marko/notification-database', function (): void {
        $reflection = new ReflectionClass(NoDriverException::class);
        $constant = $reflection->getReflectionConstant('DRIVER_PACKAGES');

        expect($constant)->not->toBeFalse()
            ->and($constant->getValue())->toContain('marko/notification-database');
    });

    it('provides suggestion with composer require command', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())->toContain('composer require marko/notification-database');
    });

    it('includes context about resolving notification interfaces', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getContext())->toContain('notification interface');
    });

    it('extends NotificationException', function (): void {
        $reflection = new ReflectionClass(NoDriverException::class);

        expect($reflection->getParentClass()->getName())->toBe(NotificationException::class);
    });
});
