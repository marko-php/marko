<?php

declare(strict_types=1);

use Marko\Validation\Contracts\RecordLookupInterface;
use Marko\Validation\Database\EntityRecordLookup;

it('binds RecordLookupInterface to EntityRecordLookup', function (): void {
    $module = require dirname(__DIR__) . '/module.php';

    expect($module)->toBe([
        'bindings' => [
            RecordLookupInterface::class => EntityRecordLookup::class,
        ],
    ]);
});

it('is a marko module named marko/validation-database that requires validation and database', function (): void {
    $composer = json_decode((string) file_get_contents(dirname(__DIR__) . '/composer.json'), true);

    expect($composer['name'])->toBe('marko/validation-database')
        ->and($composer['type'])->toBe('marko-module')
        ->and($composer['extra']['marko']['module'])->toBeTrue()
        ->and($composer['require'])->toHaveKeys(['marko/validation', 'marko/database'])
        ->and($composer['autoload']['psr-4'])->toBe(['Marko\\Validation\\Database\\' => 'src/'])
        ->and($composer)->not->toHaveKey('version');
});
