<?php

declare(strict_types=1);

it('has a README.md with title and practical one-liner', function () {
    $readmePath = dirname(__DIR__) . '/README.md';

    expect(file_exists($readmePath))->toBeTrue()
        ->and(file_get_contents($readmePath))->toContain('# marko/testing')
        ->and(file_get_contents($readmePath))->toMatch('/^# marko\/testing\n\n.+/m');
});

it('has an overview section explaining the benefit', function () {
    $readme = file_get_contents(dirname(__DIR__) . '/README.md');

    expect($readme)->toContain('## Overview');
});

it('has an installation section with composer command', function () {
    $readme = file_get_contents(dirname(__DIR__) . '/README.md');

    expect($readme)->toContain('## Installation')
        ->and($readme)->toContain('composer require marko/testing');
});

it('has a usage section with code examples for each fake', function () {
    $readme = file_get_contents(dirname(__DIR__) . '/README.md');

    expect($readme)->toContain('## Usage')
        ->and($readme)->toContain('FakeEventDispatcher')
        ->and($readme)->toContain('FakeMailer')
        ->and($readme)->toContain('FakeQueue')
        ->and($readme)->toContain('FakeSession')
        ->and($readme)->toContain('FakeCookieJar')
        ->and($readme)->toContain('FakeLogger')
        ->and($readme)->toContain('FakeConfigRepository')
        ->and($readme)->toContain('FakeAuthenticatable')
        ->and($readme)->toContain('FakeUserProvider')
        ->and($readme)->toContain('```php');
});

it('has an API reference section listing all public methods', function () {
    $readme = file_get_contents(dirname(__DIR__) . '/README.md');

    expect($readme)->toContain('## API Reference')
        ->and($readme)->toContain('dispatch(')
        ->and($readme)->toContain('assertDispatched(')
        ->and($readme)->toContain('assertNotDispatched(')
        ->and($readme)->toContain('assertDispatchedCount(')
        ->and($readme)->toContain('assertSent(')
        ->and($readme)->toContain('assertNothingSent(')
        ->and($readme)->toContain('assertSentCount(')
        ->and($readme)->toContain('assertPushed(')
        ->and($readme)->toContain('assertNotPushed(')
        ->and($readme)->toContain('assertPushedCount(')
        ->and($readme)->toContain('assertNothingPushed(')
        ->and($readme)->toContain('assertLogged(')
        ->and($readme)->toContain('assertNothingLogged(');
});

it('documents FakeGuard in the available fakes table', function () {
    $readme = file_get_contents(dirname(__DIR__) . '/README.md');

    expect($readme)->toContain('FakeGuard');
});

it('includes FakeGuard usage example with guard configuration', function () {
    $readme = file_get_contents(dirname(__DIR__) . '/README.md');

    expect($readme)->toContain('### FakeGuard')
        ->and($readme)->toContain('new FakeGuard(')
        ->and($readme)->toContain('setUser(')
        ->and($readme)->toContain('attempt(');
});

it('documents FakeGuard assertion methods', function () {
    $readme = file_get_contents(dirname(__DIR__) . '/README.md');

    expect($readme)->toContain('assertAuthenticated(')
        ->and($readme)->toContain('assertGuest(')
        ->and($readme)->toContain('assertAttempted(')
        ->and($readme)->toContain('assertNotAttempted(')
        ->and($readme)->toContain('assertLoggedOut(');
});

it('documents toHaveAttempted and toBeAuthenticated Pest expectations', function () {
    $readme = file_get_contents(dirname(__DIR__) . '/README.md');

    expect($readme)->toContain('toHaveAttempted')
        ->and($readme)->toContain('toBeAuthenticated');
});

it('follows the Package README Standards from code-standards.md', function () {
    $readme = file_get_contents(dirname(__DIR__) . '/README.md');

    expect($readme)->toContain('# marko/testing')
        ->and($readme)->toContain('## Overview')
        ->and($readme)->toContain('## Installation')
        ->and($readme)->toContain('## Usage')
        ->and($readme)->toContain('## API Reference')
        ->and($readme)->toContain('```php');
});
