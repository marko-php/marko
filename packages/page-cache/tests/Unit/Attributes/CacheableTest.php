<?php

declare(strict_types=1);

use Marko\PageCache\Attributes\Cacheable;

class CacheableFixture
{
    #[Cacheable(ttl: 3600, tags: ['products'])]
    public function index(): void {}
}

it('stores ttl and tags as public properties', function (): void {
    $cacheable = new Cacheable(ttl: 300, tags: ['products', 'catalog']);

    expect($cacheable->ttl)->toBe(300)
        ->and($cacheable->tags)->toBe(['products', 'catalog']);
});

it('accepts an empty tags array by default', function (): void {
    $cacheable = new Cacheable(ttl: 60);

    expect($cacheable->tags)->toBeEmpty();
});

it('can be discovered via reflection on a method that declares it', function (): void {
    $method = new ReflectionMethod(CacheableFixture::class, 'index');
    $attributes = $method->getAttributes(Cacheable::class);

    expect($attributes)->toHaveCount(1);

    $instance = $attributes[0]->newInstance();

    expect($instance->ttl)->toBe(3600)
        ->and($instance->tags)->toBe(['products']);
});
