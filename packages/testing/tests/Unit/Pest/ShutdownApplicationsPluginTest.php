<?php

declare(strict_types=1);

use Marko\Testing\Pest\ShutdownApplicationsPlugin;
use Marko\Testing\Pest\ShutdownApplicationsSubscriber;
use Pest\Contracts\Plugins\Bootable;
use PHPUnit\Event\Test\AfterTestMethodFinishedSubscriber;

// The plugin's effect (no risky plain it() test after TestClient::boot()) is covered end to end by
// PestPluginRegistrationTest, which runs Pest in a subprocess: PHPUnit's event facade is sealed
// while this suite runs, so boot() cannot register a subscriber here.

it('declares ShutdownApplicationsPlugin in composer.json extra.pest.plugins', function (): void {
    $composer = json_decode(
        (string) file_get_contents(dirname(__DIR__, 3) . '/composer.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($composer['extra']['pest']['plugins'] ?? [])->toContain(ShutdownApplicationsPlugin::class);
});

it('implements the Pest Bootable plugin contract', function (): void {
    expect(new ShutdownApplicationsPlugin())->toBeInstanceOf(Bootable::class);
});

it('subscribes to the event PHPUnit emits after tearDown and before its handler check', function (): void {
    expect(new ShutdownApplicationsSubscriber())->toBeInstanceOf(AfterTestMethodFinishedSubscriber::class);
});
