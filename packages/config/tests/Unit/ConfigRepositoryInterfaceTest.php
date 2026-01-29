<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;

describe('ConfigRepositoryInterface', function (): void {
    it('exists and is an interface', function (): void {
        $reflection = new ReflectionClass(ConfigRepositoryInterface::class);

        expect($reflection->isInterface())->toBeTrue();
    });

    it('defines get method without default parameter', function (): void {
        $reflection = new ReflectionClass(ConfigRepositoryInterface::class);

        expect($reflection->hasMethod('get'))->toBeTrue();

        $method = $reflection->getMethod('get');
        $params = $method->getParameters();

        expect($method->getReturnType()?->getName())->toBe('mixed')
            ->and($params)->toHaveCount(2)
            ->and($params[0]->getName())->toBe('key')
            ->and($params[0]->getType()?->getName())->toBe('string')
            ->and($params[1]->getName())->toBe('scope')
            ->and($params[1]->getType()?->getName())->toBe('string')
            ->and($params[1]->getType()?->allowsNull())->toBeTrue()
            ->and($params[1]->isDefaultValueAvailable())->toBeTrue()
            ->and($params[1]->getDefaultValue())->toBeNull();
    });

    it('defines has method with key and scope parameters returning bool', function (): void {
        $reflection = new ReflectionClass(ConfigRepositoryInterface::class);

        expect($reflection->hasMethod('has'))->toBeTrue();

        $method = $reflection->getMethod('has');
        $params = $method->getParameters();

        expect($method->getReturnType()?->getName())->toBe('bool')
            ->and($params)->toHaveCount(2)
            ->and($params[0]->getName())->toBe('key')
            ->and($params[0]->getType()?->getName())->toBe('string')
            ->and($params[1]->getName())->toBe('scope')
            ->and($params[1]->getType()?->getName())->toBe('string')
            ->and($params[1]->getType()?->allowsNull())->toBeTrue()
            ->and($params[1]->isDefaultValueAvailable())->toBeTrue()
            ->and($params[1]->getDefaultValue())->toBeNull();
    });

    it('defines getString method without default parameter', function (): void {
        $reflection = new ReflectionClass(ConfigRepositoryInterface::class);

        expect($reflection->hasMethod('getString'))->toBeTrue();

        $method = $reflection->getMethod('getString');
        $params = $method->getParameters();

        expect($method->getReturnType()?->getName())->toBe('string')
            ->and($params)->toHaveCount(2)
            ->and($params[0]->getName())->toBe('key')
            ->and($params[0]->getType()?->getName())->toBe('string')
            ->and($params[1]->getName())->toBe('scope')
            ->and($params[1]->getType()?->getName())->toBe('string')
            ->and($params[1]->getType()?->allowsNull())->toBeTrue()
            ->and($params[1]->isDefaultValueAvailable())->toBeTrue()
            ->and($params[1]->getDefaultValue())->toBeNull();
    });

    it('defines getInt method without default parameter', function (): void {
        $reflection = new ReflectionClass(ConfigRepositoryInterface::class);

        expect($reflection->hasMethod('getInt'))->toBeTrue();

        $method = $reflection->getMethod('getInt');
        $params = $method->getParameters();

        expect($method->getReturnType()?->getName())->toBe('int')
            ->and($params)->toHaveCount(2)
            ->and($params[0]->getName())->toBe('key')
            ->and($params[0]->getType()?->getName())->toBe('string')
            ->and($params[1]->getName())->toBe('scope')
            ->and($params[1]->getType()?->getName())->toBe('string')
            ->and($params[1]->getType()?->allowsNull())->toBeTrue()
            ->and($params[1]->isDefaultValueAvailable())->toBeTrue()
            ->and($params[1]->getDefaultValue())->toBeNull();
    });

    it('defines getBool method without default parameter', function (): void {
        $reflection = new ReflectionClass(ConfigRepositoryInterface::class);

        expect($reflection->hasMethod('getBool'))->toBeTrue();

        $method = $reflection->getMethod('getBool');
        $params = $method->getParameters();

        expect($method->getReturnType()?->getName())->toBe('bool')
            ->and($params)->toHaveCount(2)
            ->and($params[0]->getName())->toBe('key')
            ->and($params[0]->getType()?->getName())->toBe('string')
            ->and($params[1]->getName())->toBe('scope')
            ->and($params[1]->getType()?->getName())->toBe('string')
            ->and($params[1]->getType()?->allowsNull())->toBeTrue()
            ->and($params[1]->isDefaultValueAvailable())->toBeTrue()
            ->and($params[1]->getDefaultValue())->toBeNull();
    });

    it('defines getFloat method without default parameter', function (): void {
        $reflection = new ReflectionClass(ConfigRepositoryInterface::class);

        expect($reflection->hasMethod('getFloat'))->toBeTrue();

        $method = $reflection->getMethod('getFloat');
        $params = $method->getParameters();

        expect($method->getReturnType()?->getName())->toBe('float')
            ->and($params)->toHaveCount(2)
            ->and($params[0]->getName())->toBe('key')
            ->and($params[0]->getType()?->getName())->toBe('string')
            ->and($params[1]->getName())->toBe('scope')
            ->and($params[1]->getType()?->getName())->toBe('string')
            ->and($params[1]->getType()?->allowsNull())->toBeTrue()
            ->and($params[1]->isDefaultValueAvailable())->toBeTrue()
            ->and($params[1]->getDefaultValue())->toBeNull();
    });

    it('defines getArray method without default parameter', function (): void {
        $reflection = new ReflectionClass(ConfigRepositoryInterface::class);

        expect($reflection->hasMethod('getArray'))->toBeTrue();

        $method = $reflection->getMethod('getArray');
        $params = $method->getParameters();

        expect($method->getReturnType()?->getName())->toBe('array')
            ->and($params)->toHaveCount(2)
            ->and($params[0]->getName())->toBe('key')
            ->and($params[0]->getType()?->getName())->toBe('string')
            ->and($params[1]->getName())->toBe('scope')
            ->and($params[1]->getType()?->getName())->toBe('string')
            ->and($params[1]->getType()?->allowsNull())->toBeTrue()
            ->and($params[1]->isDefaultValueAvailable())->toBeTrue()
            ->and($params[1]->getDefaultValue())->toBeNull();
    });

    it('defines all method with scope parameter returning array', function (): void {
        $reflection = new ReflectionClass(ConfigRepositoryInterface::class);

        expect($reflection->hasMethod('all'))->toBeTrue();

        $method = $reflection->getMethod('all');
        $params = $method->getParameters();

        expect($method->getReturnType()?->getName())->toBe('array')
            ->and($params)->toHaveCount(1)
            ->and($params[0]->getName())->toBe('scope')
            ->and($params[0]->getType()?->getName())->toBe('string')
            ->and($params[0]->getType()?->allowsNull())->toBeTrue()
            ->and($params[0]->isDefaultValueAvailable())->toBeTrue()
            ->and($params[0]->getDefaultValue())->toBeNull();
    });

    it('documents dot notation support for nested keys in get method', function (): void {
        $reflection = new ReflectionClass(ConfigRepositoryInterface::class);
        $method = $reflection->getMethod('get');
        $docComment = $method->getDocComment();

        expect($docComment)->toBeString()
            ->and($docComment)->toContain('dot notation')
            ->and($docComment)->toContain('database.host');
    });

    it('defines withScope method with scope parameter returning ConfigRepositoryInterface', function (): void {
        $reflection = new ReflectionClass(ConfigRepositoryInterface::class);

        expect($reflection->hasMethod('withScope'))->toBeTrue();

        $method = $reflection->getMethod('withScope');
        $params = $method->getParameters();

        expect($method->getReturnType()?->getName())->toBe(ConfigRepositoryInterface::class)
            ->and($params)->toHaveCount(1)
            ->and($params[0]->getName())->toBe('scope')
            ->and($params[0]->getType()?->getName())->toBe('string');
    });
});
