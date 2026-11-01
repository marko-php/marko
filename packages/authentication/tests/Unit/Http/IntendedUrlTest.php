<?php

declare(strict_types=1);

use Marko\Authentication\Contracts\IntendedUrlInterface;
use Marko\Authentication\Http\IntendedUrl;
use Marko\Routing\Http\Request;
use Marko\Testing\Fake\FakeSession;

function intendedUrlTestSession(): FakeSession
{
    $session = new FakeSession();
    $session->start();

    return $session;
}

it('implements IntendedUrlInterface', function (): void {
    expect(new IntendedUrl(intendedUrlTestSession()))->toBeInstanceOf(IntendedUrlInterface::class);
});

it('remembers the path and query string of a GET request and pulls it once', function (): void {
    $intendedUrl = new IntendedUrl(intendedUrlTestSession());

    $intendedUrl->remember(new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/settings/two-factor?tab=setup',
    ]));

    expect($intendedUrl->pull('/home'))->toBe('/settings/two-factor?tab=setup')
        ->and($intendedUrl->pull('/home'))->toBe('/home');
});

it('returns the default when nothing is remembered', function (): void {
    expect(new IntendedUrl(intendedUrlTestSession())->pull('/dashboard'))->toBe('/dashboard');
});

it('forgets the intended URL on a non-GET request', function (): void {
    $intendedUrl = new IntendedUrl(intendedUrlTestSession());
    $intendedUrl->remember(new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/settings']));

    $intendedUrl->remember(new Request(server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/account/delete']));

    expect($intendedUrl->pull('/home'))->toBe('/home');
});

it('collapses leading slashes and backslashes so the URL stays on this host', function (string $uri): void {
    $intendedUrl = new IntendedUrl(intendedUrlTestSession());

    $intendedUrl->remember(new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri]));

    expect($intendedUrl->pull('/home'))->toBe('/evil.example/path');
})->with([
    '//evil.example/path',
    '/\\evil.example/path',
    '///evil.example/path',
]);
