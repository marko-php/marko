<?php

declare(strict_types=1);

use Marko\Testing\Pest\ExpectationsPlugin;
use Pest\Contracts\Plugins\Bootable;
use Pest\Expectation;

const MARKO_TESTING_EXPECTATIONS = [
    'toHaveDispatched',
    'toHaveBroadcast',
    'toHaveSent',
    'toHaveSentRequest',
    'toHaveNotified',
    'toHavePushed',
    'toHaveStored',
    'toHaveLogged',
    'toHaveAttempted',
    'toBeAuthenticated',
    'toHaveStatus',
    'toHaveJsonPath',
    'toHaveSession',
    'toHaveSessionErrors',
];

/**
 * Runs $register with Pest's expectation registry emptied, returns which Marko
 * expectations it registered, then restores the registry for the rest of the suite.
 *
 * @return array<int, string>
 */
function registeredMarkoExpectationsAfter(Closure $register): array
{
    $extends = new ReflectionProperty(Expectation::class, 'extends');
    $original = $extends->getValue();
    $extends->setValue(null, []);

    try {
        $register();

        return array_values(array_filter(
            MARKO_TESTING_EXPECTATIONS,
            fn (string $name): bool => Expectation::hasExtend($name),
        ));
    } finally {
        $extends->setValue(null, $original);
    }
}

it('declares ExpectationsPlugin in composer.json extra.pest.plugins', function (): void {
    $composer = json_decode(
        (string) file_get_contents(dirname(__DIR__, 3) . '/composer.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($composer['extra']['pest']['plugins'] ?? [])->toContain(ExpectationsPlugin::class);
});

it('implements the Pest Bootable plugin contract', function (): void {
    expect(new ExpectationsPlugin())->toBeInstanceOf(Bootable::class);
});

it('no longer lists the expectations file in autoload.files', function (): void {
    $composer = json_decode(
        (string) file_get_contents(dirname(__DIR__, 3) . '/composer.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($composer['autoload'])->not->toHaveKey('files');
});

it('suggests pestphp/pest for the expectations', function (): void {
    $composer = json_decode(
        (string) file_get_contents(dirname(__DIR__, 3) . '/composer.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($composer['suggest'] ?? [])->toHaveKey('pestphp/pest');
});

it('registers every expectation when the plugin boots', function (): void {
    $registered = registeredMarkoExpectationsAfter(function (): void {
        new ExpectationsPlugin()->boot();
    });

    expect($registered)->toBe(MARKO_TESTING_EXPECTATIONS);
});

it('registers every expectation when the backward-compatible shim is required', function (): void {
    $registered = registeredMarkoExpectationsAfter(function (): void {
        require dirname(__DIR__, 3) . '/src/Pest/Expectations.php';
    });

    expect($registered)->toBe(MARKO_TESTING_EXPECTATIONS);
});
