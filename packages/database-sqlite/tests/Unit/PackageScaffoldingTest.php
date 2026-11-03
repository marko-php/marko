<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Tests\Unit;

describe('database-sqlite package', function (): void {
    it(
        'has a composer.json named marko/database-sqlite requiring ext-pdo_sqlite marko/core and marko/database',
        function (): void {
            $composer = json_decode(
                file_get_contents(__DIR__ . '/../../composer.json'),
                true,
                flags: JSON_THROW_ON_ERROR,
            );

            expect($composer['name'])->toBe('marko/database-sqlite')
                ->and($composer['require'])->toHaveKeys(['ext-pdo_sqlite', 'marko/core', 'marko/database'])
                ->and($composer)->not->toHaveKey('version');
        },
    );

    it('autoloads the Marko\Database\Sqlite namespace from src', function (): void {
        $composer = json_decode(file_get_contents(__DIR__ . '/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);

        expect($composer['autoload']['psr-4'])->toBe(['Marko\\Database\\Sqlite\\' => 'src/']);
    });

    it(
        'suggests marko/database-sqlite from the skeleton and framework packages with the known-drivers description',
        function (): void {
            $drivers = (static fn (): array => require __DIR__ . '/../../../database/known-drivers.php')();

            foreach (['skeleton', 'framework'] as $package) {
                $composer = json_decode(
                    file_get_contents(__DIR__ . '/../../../' . $package . '/composer.json'),
                    true,
                    flags: JSON_THROW_ON_ERROR,
                );

                expect($composer['suggest']['marko/database-sqlite'])->toBe($drivers['marko/database-sqlite']);
            }
        },
    );

    it('ships a module.php that returns an array', function (): void {
        $module = (static fn (): mixed => require __DIR__ . '/../../module.php')();

        expect($module)->toBeArray()
            ->and($module['bindings'])->toBeArray();
    });
});
