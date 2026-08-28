<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\GuardRails;

use Marko\Core\Module\ModuleManifest;
use Marko\Roadrunner\Exceptions\UnsafePackageException;
use Marko\Roadrunner\GuardRails\UnsafePackageChecker;

use function Marko\Roadrunner\Tests\catchThrowable;
use function Marko\Roadrunner\Tests\createModuleRepository;

use Marko\Sse\StreamingResponse;
use Marko\Testing\Fake\FakeConfigRepository;

describe('UnsafePackageChecker', function (): void {
    it('refuses to boot when the sse package is installed', function (): void {
        $moduleRepository = createModuleRepository([
            new ModuleManifest(name: 'marko/sse', version: '1.0.0'),
        ]);
        $configRepository = new FakeConfigRepository([
            'roadrunner.acknowledged_unsafe_packages' => [],
        ]);
        $checker = new UnsafePackageChecker($moduleRepository, $configRepository);

        expect(fn () => $checker->check())->toThrow(UnsafePackageException::class);
    });

    it('explains why sse cannot work in worker mode when refusing', function (): void {
        $moduleRepository = createModuleRepository([
            new ModuleManifest(name: 'marko/sse', version: '1.0.0'),
        ]);
        $configRepository = new FakeConfigRepository([
            'roadrunner.acknowledged_unsafe_packages' => [],
        ]);
        $checker = new UnsafePackageChecker($moduleRepository, $configRepository);
        $exception = catchThrowable(fn () => $checker->check());

        expect($exception)->toBeInstanceOf(UnsafePackageException::class)
            ->and($exception->getMessage())->toContain('marko/sse')
            ->and($exception->getMessage())->toContain('worker')
            ->and($exception->getMessage())->toContain('stream');
    });

    it('names the config override in the refusal message', function (): void {
        $moduleRepository = createModuleRepository([
            new ModuleManifest(name: 'marko/sse', version: '1.0.0'),
        ]);
        $configRepository = new FakeConfigRepository([
            'roadrunner.acknowledged_unsafe_packages' => [],
        ]);
        $checker = new UnsafePackageChecker($moduleRepository, $configRepository);
        $exception = catchThrowable(fn () => $checker->check());

        expect($exception)->toBeInstanceOf(UnsafePackageException::class)
            ->and($exception->getSuggestion())->toContain('roadrunner.acknowledged_unsafe_packages')
            ->and($exception->getSuggestion())->toContain('marko/sse');
    });

    it('boots with a warning when the sse package is explicitly acknowledged in config', function (): void {
        $moduleRepository = createModuleRepository([
            new ModuleManifest(name: 'marko/sse', version: '1.0.0'),
        ]);
        $configRepository = new FakeConfigRepository([
            'roadrunner.acknowledged_unsafe_packages' => ['marko/sse'],
        ]);
        $checker = new UnsafePackageChecker($moduleRepository, $configRepository);
        $warnings = $checker->check();

        expect($warnings)->toHaveCount(1)
            ->and($warnings[0])->toContain('marko/sse')
            ->and($warnings[0])->toContain('500');
    });

    it('warns but continues when the debugbar package is installed', function (): void {
        $moduleRepository = createModuleRepository([
            new ModuleManifest(name: 'marko/debugbar', version: '1.0.0'),
        ]);
        $configRepository = new FakeConfigRepository([
            'roadrunner.acknowledged_unsafe_packages' => [],
        ]);
        $checker = new UnsafePackageChecker($moduleRepository, $configRepository);
        $warnings = $checker->check();

        expect($warnings)->toHaveCount(1)
            ->and($warnings[0])->toContain('marko/debugbar');
    });

    it('boots without complaint when no unsafe package is installed', function (): void {
        $moduleRepository = createModuleRepository([
            new ModuleManifest(name: 'marko/core', version: '1.0.0'),
            new ModuleManifest(name: 'marko/routing', version: '1.0.0'),
        ]);
        $configRepository = new FakeConfigRepository([]);
        $checker = new UnsafePackageChecker($moduleRepository, $configRepository);
        $warnings = $checker->check();

        expect($warnings)->toBeEmpty();
    });

    it('reads installed modules from the module repository rather than class existence', function (): void {
        // marko/sse is a real require-dev dependency of this package (Marko\Sse\StreamingResponse
        // is autoloadable right now), but the module repository below does not report it as an
        // installed module. If the checker fell back to class_exists(), it would still refuse to
        // boot here; because it consults the repository only, it must not.
        expect(class_exists(StreamingResponse::class))->toBeTrue();

        $moduleRepository = createModuleRepository([
            new ModuleManifest(name: 'marko/core', version: '1.0.0'),
        ]);
        $configRepository = new FakeConfigRepository([]);
        $checker = new UnsafePackageChecker($moduleRepository, $configRepository);
        $warnings = $checker->check();

        expect($warnings)->toBeEmpty();
    });

    it('runs guard rail checks once rather than per request', function (): void {
        $moduleRepositoryCalls = 0;
        $moduleRepository = createModuleRepository(
            [new ModuleManifest(name: 'marko/debugbar', version: '1.0.0')],
            onAll: function () use (&$moduleRepositoryCalls): void {
                $moduleRepositoryCalls++;
            },
        );
        $configRepository = new FakeConfigRepository([]);
        $checker = new UnsafePackageChecker($moduleRepository, $configRepository);
        $checker->check();

        expect($moduleRepositoryCalls)->toBe(1);
    });
});
