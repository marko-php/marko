<?php

declare(strict_types=1);

use Marko\PageCache\Attributes\Cacheable;
use Marko\PageCache\Contracts\CacheTagProviderInterface;
use Marko\Routing\Http\Request;

it('defines CacheTagProviderInterface with a tags method taking Request and Cacheable and returning an array of strings', function (): void {
    $method = new ReflectionMethod(CacheTagProviderInterface::class, 'tags');
    $params = $method->getParameters();

    expect($params)->toHaveCount(2)
        ->and($params[0]->getType()->getName())->toBe(Request::class)
        ->and($params[1]->getType()->getName())->toBe(Cacheable::class)
        ->and($method->getReturnType()->getName())->toBe('array');
});

it('places CacheTagProviderInterface under the Marko PageCache Contracts namespace', function (): void {
    expect(interface_exists(CacheTagProviderInterface::class))->toBeTrue()
        ->and((new ReflectionClass(CacheTagProviderInterface::class))->getNamespaceName())
        ->toBe('Marko\PageCache\Contracts');
});
