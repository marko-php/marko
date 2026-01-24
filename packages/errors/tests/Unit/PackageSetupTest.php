<?php

declare(strict_types=1);

use Marko\Errors\Contracts\ErrorHandlerInterface;
use Marko\Errors\Contracts\ErrorReporterInterface;
use Marko\Errors\ErrorReport;
use Marko\Errors\Severity;

describe('Package Setup', function (): void {
    describe('composer.json', function (): void {
        beforeEach(function (): void {
            $this->composerJson = json_decode(
                file_get_contents(__DIR__ . '/../../composer.json'),
                true,
            );
        });

        it('has valid composer.json with name marko/errors', function (): void {
            expect($this->composerJson['name'])->toBe('marko/errors');
        });

        it('requires php 8.5 or higher', function (): void {
            expect($this->composerJson['require']['php'])->toBe('^8.5');
        });

        it('requires marko/core for MarkoException', function (): void {
            expect($this->composerJson['require'])->toHaveKey('marko/core');
        });

        it('has PSR-4 autoloading for Marko\\Errors namespace', function (): void {
            expect($this->composerJson['autoload']['psr-4'])
                ->toHaveKey('Marko\\Errors\\')
                ->and($this->composerJson['autoload']['psr-4']['Marko\\Errors\\'])
                ->toBe('src/');
        });
    });

    describe('exports', function (): void {
        it('exports ErrorHandlerInterface', function (): void {
            expect(interface_exists(ErrorHandlerInterface::class))->toBeTrue();
        });

        it('exports ErrorReporterInterface', function (): void {
            expect(interface_exists(ErrorReporterInterface::class))->toBeTrue();
        });

        it('exports ErrorReport', function (): void {
            expect(class_exists(ErrorReport::class))->toBeTrue();
        });

        it('exports Severity', function (): void {
            expect(enum_exists(Severity::class))->toBeTrue();
        });
    });
});
