<?php

declare(strict_types=1);

use Marko\Authentication\Contracts\CookieJarInterface;
use Marko\Testing\Fake\FakeCookieJar;

it('FakeCookieJar implements CookieJarInterface', function () {
    $jar = new FakeCookieJar();

    expect($jar)->toBeInstanceOf(CookieJarInterface::class);
});

it('FakeCookieJar stores and retrieves cookies in memory', function () {
    $jar = new FakeCookieJar();

    $jar->set('session', 'abc123');
    $jar->set('user_id', '42', 60);

    expect($jar->get('session'))->toBe('abc123')
        ->and($jar->get('user_id'))->toBe('42')
        ->and($jar->cookies)->toBe(['session' => 'abc123', 'user_id' => '42']);
});

it('FakeCookieJar deletes cookies', function () {
    $jar = new FakeCookieJar();

    $jar->set('session', 'abc123');
    $jar->set('remember', 'xyz');

    $jar->delete('session');

    expect($jar->get('session'))->toBeNull()
        ->and($jar->get('remember'))->toBe('xyz')
        ->and($jar->cookies)->toHaveKey('remember')
        ->and($jar->cookies)->not->toHaveKey('session');
});

it('FakeCookieJar returns null for missing cookies', function () {
    $jar = new FakeCookieJar();

    expect($jar->get('nonexistent'))->toBeNull()
        ->and($jar->cookies)->toBeEmpty();
});
