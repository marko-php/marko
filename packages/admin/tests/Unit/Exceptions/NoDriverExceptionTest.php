<?php

declare(strict_types=1);

use Marko\Admin\Exceptions\AdminException;
use Marko\Admin\Exceptions\NoDriverException;

describe('NoDriverException', function (): void {
    it('has DRIVER_PACKAGES constant listing marko/admin-api, marko/admin-auth, and marko/admin-panel', function (): void {
        $reflection = new ReflectionClass(NoDriverException::class);
        $constants = $reflection->getConstants();

        expect($constants)->toHaveKey('DRIVER_PACKAGES')
            ->and($constants['DRIVER_PACKAGES'])->toBe([
                'marko/admin-api',
                'marko/admin-auth',
                'marko/admin-panel',
            ]);
    });

    it('provides suggestion with composer require commands for all driver packages', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())->toContain('composer require marko/admin-api')
            ->and($exception->getSuggestion())->toContain('composer require marko/admin-auth')
            ->and($exception->getSuggestion())->toContain('composer require marko/admin-panel');
    });

    it('includes context about resolving admin interfaces', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getContext())->toContain('admin interface');
    });

    it('extends AdminException', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception)->toBeInstanceOf(AdminException::class);
    });
});
