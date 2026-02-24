<?php

declare(strict_types=1);

use Marko\Http\Contracts\HttpClientInterface;
use Marko\Http\HttpResponse;

describe('HttpClientInterface', function (): void {
    it('defines HttpClientInterface with request method', function (): void {
        $reflection = new ReflectionClass(HttpClientInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('request'))->toBeTrue();

        $method = $reflection->getMethod('request');

        expect($method->isPublic())->toBeTrue();

        $parameters = $method->getParameters();
        expect($parameters)->toHaveCount(3)
            ->and($parameters[0]->getName())->toBe('method')
            ->and($parameters[0]->getType()?->getName())->toBe('string')
            ->and($parameters[1]->getName())->toBe('url')
            ->and($parameters[1]->getType()?->getName())->toBe('string')
            ->and($parameters[2]->getName())->toBe('options')
            ->and($parameters[2]->getType()?->getName())->toBe('array')
            ->and($parameters[2]->isDefaultValueAvailable())->toBeTrue();

        $returnType = $method->getReturnType();
        expect($returnType)->not->toBeNull()
            ->and($returnType->getName())->toBe(HttpResponse::class);
    });

    it('defines HttpClientInterface with convenience methods', function (): void {
        $reflection = new ReflectionClass(HttpClientInterface::class);
        $methods = ['get', 'post', 'put', 'patch', 'delete'];

        foreach ($methods as $methodName) {
            expect($reflection->hasMethod($methodName))->toBeTrue();

            $method = $reflection->getMethod($methodName);

            expect($method->isPublic())->toBeTrue();

            $parameters = $method->getParameters();
            expect($parameters)->toHaveCount(2)
                ->and($parameters[0]->getName())->toBe('url')
                ->and($parameters[0]->getType()?->getName())->toBe('string')
                ->and($parameters[1]->getName())->toBe('options')
                ->and($parameters[1]->getType()?->getName())->toBe('array');

            $returnType = $method->getReturnType();
            expect($returnType)->not->toBeNull()
                ->and($returnType->getName())->toBe(HttpResponse::class);
        }
    });
});
