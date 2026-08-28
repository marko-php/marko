<?php

declare(strict_types=1);

use Marko\Routing\Exceptions\CookieException;
use Marko\Routing\Http\Cookie;

it('renders name and value as a set-cookie string', function (): void {
    $cookie = new Cookie(name: 'session_id', value: 'abc123');

    expect($cookie->toSetCookieString())->toBe('session_id=abc123');
});

it('renders the path attribute when provided', function (): void {
    $cookie = new Cookie(name: 'session_id', value: 'abc123', path: '/');

    expect($cookie->toSetCookieString())->toBe('session_id=abc123; Path=/');
});

it('renders the domain attribute when provided', function (): void {
    $cookie = new Cookie(name: 'session_id', value: 'abc123', domain: 'example.com');

    expect($cookie->toSetCookieString())->toBe('session_id=abc123; Domain=example.com');
});

it('renders expires as an rfc 7231 formatted date', function (): void {
    $cookie = new Cookie(name: 'session_id', value: 'abc123', expires: 1704067200);

    expect($cookie->toSetCookieString())->toBe('session_id=abc123; Expires=Mon, 01 Jan 2024 00:00:00 GMT');
});

it('omits the expires attribute entirely for a browser session cookie', function (): void {
    $cookie = new Cookie(name: 'session_id', value: 'abc123', expires: 0);

    expect($cookie->toSetCookieString())->toBe('session_id=abc123');
});

it('renders secure and httponly flags only when enabled', function (): void {
    $enabled = new Cookie(name: 'session_id', value: 'abc123', secure: true, httpOnly: true);
    $disabled = new Cookie(name: 'session_id', value: 'abc123');

    expect($enabled->toSetCookieString())->toBe('session_id=abc123; Secure; HttpOnly')
        ->and($disabled->toSetCookieString())->toBe('session_id=abc123');
});

it('renders the samesite attribute when provided', function (): void {
    $cookie = new Cookie(name: 'session_id', value: 'abc123', sameSite: 'Lax');

    expect($cookie->toSetCookieString())->toBe('session_id=abc123; SameSite=Lax');
});

it('encodes a value containing characters that are illegal in a set-cookie header', function (): void {
    $cookie = new Cookie(name: 'data', value: 'a; b');

    expect($cookie->toSetCookieString())->toBe('data=a%3B%20b');
});

it('throws when the cookie name contains an invalid character', function (): void {
    expect(fn (): Cookie => new Cookie(name: 'sess ion', value: 'abc123'))
        ->toThrow(CookieException::class);
});

it('throws when samesite is none without the secure flag', function (): void {
    expect(fn (): Cookie => new Cookie(name: 'session_id', value: 'abc123', sameSite: 'None'))
        ->toThrow(CookieException::class);
});

it('exposes the name path and domain used to identify a cookie', function (): void {
    $cookie = new Cookie(name: 'session_id', value: 'abc123', path: '/admin', domain: 'example.com');

    expect($cookie->name())->toBe('session_id')
        ->and($cookie->path())->toBe('/admin')
        ->and($cookie->domain())->toBe('example.com');
});

it('defaults path and domain to null when not provided', function (): void {
    $cookie = new Cookie(name: 'session_id', value: 'abc123');

    expect($cookie->path())->toBeNull()
        ->and($cookie->domain())->toBeNull();
});
