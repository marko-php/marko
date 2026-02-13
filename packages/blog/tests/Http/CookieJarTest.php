<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Http;

use Marko\Blog\Contracts\CookieJarInterface;
use Marko\Blog\Http\CookieJar;
use ReflectionClass;

it('implements CookieJarInterface', function (): void {
    $cookieJar = new CookieJar();

    expect($cookieJar)->toBeInstanceOf(CookieJarInterface::class);
});

it('is not final to allow Preference overrides', function (): void {
    $reflection = new ReflectionClass(CookieJar::class);

    expect($reflection->isFinal())->toBeFalse();
});

it('has set method with correct signature', function (): void {
    $reflection = new ReflectionClass(CookieJar::class);
    $method = $reflection->getMethod('set');

    expect($method->getNumberOfParameters())->toBe(3)
        ->and($method->getParameters()[0]->getName())->toBe('name')
        ->and($method->getParameters()[0]->getType()->getName())->toBe('string')
        ->and($method->getParameters()[1]->getName())->toBe('value')
        ->and($method->getParameters()[1]->getType()->getName())->toBe('string')
        ->and($method->getParameters()[2]->getName())->toBe('options')
        ->and($method->getParameters()[2]->getType()->getName())->toBe('array')
        ->and($method->getParameters()[2]->isDefaultValueAvailable())->toBeTrue()
        ->and($method->getParameters()[2]->getDefaultValue())->toBe([]);
});

it('returns void from set method', function (): void {
    $reflection = new ReflectionClass(CookieJar::class);
    $method = $reflection->getMethod('set');

    expect($method->getReturnType()->getName())->toBe('void');
});
