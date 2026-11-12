<?php

declare(strict_types=1);

use Marko\Core\Container\ArgumentResolver;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Exceptions\ArgumentException;
use Marko\Core\Tests\Unit\Container\Fixtures\T015Color;
use Marko\Core\Tests\Unit\Container\Fixtures\T015Contract;
use Marko\Core\Tests\Unit\Container\Fixtures\T015Impl;
use Marko\Core\Tests\Unit\Container\Fixtures\T015Other;
use Marko\Core\Tests\Unit\Container\Fixtures\T015Subject;

function t015Parameter(
    string $name,
): ReflectionParameter {
    return array_find(
        new ReflectionClass(T015Subject::class)->getConstructor()->getParameters(),
        fn (ReflectionParameter $parameter): bool => $parameter->getName() === $name,
    ) ?? throw new LogicException("No parameter $name");
}

describe('ArgumentResolver value resolution', function (): void {
    it('passes a literal to a builtin typed parameter', function (): void {
        $resolver = new ArgumentResolver();
        $container = new Container();

        expect($resolver->resolveValue(t015Parameter('name'), 'marko', T015Subject::class, $container))
            ->toBe('marko')
            ->and($resolver->resolveValue(t015Parameter('ratio'), 2, T015Subject::class, $container))
            ->toBe(2.0);
    });

    it('resolves a class name through the container for a class typed parameter', function (): void {
        $resolver = new ArgumentResolver();
        $container = new Container();
        $container->singleton(T015Impl::class);

        $resolved = $resolver->resolveValue(t015Parameter('impl'), T015Impl::class, T015Subject::class, $container);

        expect($resolved)
            ->toBeInstanceOf(T015Impl::class)
            ->toBe($container->get(T015Impl::class));
    });

    it('applies bindings when resolving a class name given for an interface typed parameter', function (): void {
        $resolver = new ArgumentResolver();
        $container = new Container();
        $container->bind(T015Contract::class, T015Other::class);

        $resolved = $resolver->resolveValue(
            t015Parameter('contract'),
            T015Contract::class,
            T015Subject::class,
            $container,
        );

        expect($resolved)->toBeInstanceOf(T015Other::class);
    });

    it('calls a closure value with the container and passes its result', function (): void {
        $resolver = new ArgumentResolver();
        $container = new Container();
        $received = null;

        $resolved = $resolver->resolveValue(
            t015Parameter('name'),
            function (ContainerInterface $given) use (&$received): string {
                $received = $given;

                return 'built';
            },
            T015Subject::class,
            $container,
        );

        expect($resolved)
            ->toBe('built')
            ->and($received)->toBe($container);
    });

    it('passes the closure returned by a factory to a Closure typed parameter', function (): void {
        $resolver = new ArgumentResolver();
        $inner = fn (): string => 'inner';

        $resolved = $resolver->resolveValue(
            t015Parameter('factory'),
            fn () => $inner,
            T015Subject::class,
            new Container(),
        );

        expect($resolved)->toBe($inner);
    });

    it('passes null to a nullable class typed parameter', function (): void {
        $resolver = new ArgumentResolver();

        $resolved = $resolver->resolveValue(t015Parameter('maybe'), null, T015Subject::class, new Container());

        expect($resolved)->toBeNull();
    });

    it('passes an enum case to an enum typed parameter', function (): void {
        $resolver = new ArgumentResolver();

        $resolved = $resolver->resolveValue(
            t015Parameter('color'),
            T015Color::Red,
            T015Subject::class,
            new Container(),
        );

        expect($resolved)->toBe(T015Color::Red);
    });

    it('throws when a factory returns a value of the wrong type', function (): void {
        $resolver = new ArgumentResolver();

        expect(fn () => $resolver->resolveValue(
            t015Parameter('contract'),
            fn () => new stdClass(),
            T015Subject::class,
            new Container(),
        ))->toThrow(ArgumentException::class, 'must be of type');
    });
});
