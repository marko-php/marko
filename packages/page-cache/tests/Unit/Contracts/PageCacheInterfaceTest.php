<?php

declare(strict_types=1);

use Marko\PageCache\CachePolicy;
use Marko\PageCache\Contracts\PageCacheInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

it('declares lookup with Request parameter and nullable Response return', function (): void {
    $method = new ReflectionMethod(PageCacheInterface::class, 'lookup');
    $params = $method->getParameters();

    expect($params)->toHaveCount(1)
        ->and($params[0]->getType()->getName())->toBe(Request::class)
        ->and($method->getReturnType()->getName())->toBe(Response::class)
        ->and($method->getReturnType()->allowsNull())->toBeTrue();
});

it('declares store with Request, Response, and CachePolicy parameters returning Response', function (): void {
    $method = new ReflectionMethod(PageCacheInterface::class, 'store');
    $params = $method->getParameters();

    expect($params)->toHaveCount(3)
        ->and($params[0]->getType()->getName())->toBe(Request::class)
        ->and($params[1]->getType()->getName())->toBe(Response::class)
        ->and($params[2]->getType()->getName())->toBe(CachePolicy::class)
        ->and($method->getReturnType()->getName())->toBe(Response::class)
        ->and($method->getReturnType()->allowsNull())->toBeFalse();
});

it('declares purgeUrl with string parameter returning bool', function (): void {
    $method = new ReflectionMethod(PageCacheInterface::class, 'purgeUrl');
    $params = $method->getParameters();

    expect($params)->toHaveCount(1)
        ->and($params[0]->getType()->getName())->toBe('string')
        ->and($method->getReturnType()->getName())->toBe('bool');
});

it('declares purgeTag with string parameter returning bool', function (): void {
    $method = new ReflectionMethod(PageCacheInterface::class, 'purgeTag');
    $params = $method->getParameters();

    expect($params)->toHaveCount(1)
        ->and($params[0]->getType()->getName())->toBe('string')
        ->and($method->getReturnType()->getName())->toBe('bool');
});

it('declares clear with no parameters returning bool', function (): void {
    $method = new ReflectionMethod(PageCacheInterface::class, 'clear');
    $params = $method->getParameters();

    expect($params)->toHaveCount(0)
        ->and($method->getReturnType()->getName())->toBe('bool');
});
