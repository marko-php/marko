<?php

declare(strict_types=1);

use Marko\Cache\Contracts\CacheInterface;

it('declares an increment method on CacheInterface accepting a string key and int ttl', function (): void {
    $reflection = new ReflectionClass(CacheInterface::class);

    expect($reflection->hasMethod('increment'))->toBeTrue();

    $method = $reflection->getMethod('increment');
    $parameters = $method->getParameters();

    expect(count($parameters))->toBe(2);

    $keyParam = $parameters[0];
    expect($keyParam->getName())->toBe('key')
        ->and($keyParam->getType())->toBeInstanceOf(ReflectionNamedType::class)
        ->and($keyParam->getType()->getName())->toBe('string');

    $ttlParam = $parameters[1];
    expect($ttlParam->getName())->toBe('ttl')
        ->and($ttlParam->getType())->toBeInstanceOf(ReflectionNamedType::class)
        ->and($ttlParam->getType()->getName())->toBe('int');
});

it('declares increment as returning int', function (): void {
    $reflection = new ReflectionClass(CacheInterface::class);

    $method = $reflection->getMethod('increment');
    $returnType = $method->getReturnType();

    expect($returnType)->not->toBeNull()
        ->and($returnType)->toBeInstanceOf(ReflectionNamedType::class)
        ->and($returnType->getName())->toBe('int');
});

it('documents increment as throwing InvalidKeyException', function (): void {
    $reflection = new ReflectionClass(CacheInterface::class);

    $method = $reflection->getMethod('increment');
    $docComment = $method->getDocComment();

    expect($docComment)->not->toBeFalse()
        ->and($docComment)->toContain('@throws InvalidKeyException');
});
