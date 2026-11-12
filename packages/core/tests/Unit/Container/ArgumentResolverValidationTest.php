<?php

declare(strict_types=1);

use Marko\Core\Container\ArgumentResolver;
use Marko\Core\Exceptions\ArgumentException;
use Marko\Core\Tests\Unit\Container\Fixtures\T004Color;
use Marko\Core\Tests\Unit\Container\Fixtures\T004EnumSubject;
use Marko\Core\Tests\Unit\Container\Fixtures\T004Impl;
use Marko\Core\Tests\Unit\Container\Fixtures\T004NoConstructor;
use Marko\Core\Tests\Unit\Container\Fixtures\T004Subject;
use Marko\Core\Tests\Unit\Container\Fixtures\T004Suit;
use Marko\Core\Tests\Unit\Container\Fixtures\T004Union;
use Marko\Core\Tests\Unit\Container\Fixtures\T004Unrelated;
use Marko\Core\Tests\Unit\Container\Fixtures\T004Variadic;

describe('ArgumentResolver validation', function (): void {
    it('accepts values that match each parameter type', function (): void {
        $resolver = new ArgumentResolver();

        $resolver->validate(new ReflectionClass(T004Subject::class), [
            'name' => 'marko',
            'port' => 90,
            'ratio' => 2,
            'flag' => true,
            'list' => [1, 2],
            'contract' => T004Impl::class,
            'maybe' => null,
            'built' => fn () => 'built',
        ]);

        expect(true)->toBeTrue();
    });

    it('throws when the parameter name does not exist on the constructor', function (): void {
        $resolver = new ArgumentResolver();

        try {
            $resolver->validate(new ReflectionClass(T004Subject::class), ['missing' => 'x']);
            $this->fail('Expected ArgumentException');
        } catch (ArgumentException $exception) {
            expect($exception->getMessage())->toContain("'\$missing'")
                ->and($exception->getSuggestion())->toContain('name, port');
        }
    });

    it('throws for a variadic parameter', function (): void {
        $resolver = new ArgumentResolver();

        expect(fn () => $resolver->validate(new ReflectionClass(T004Variadic::class), ['items' => 'a']))
            ->toThrow(ArgumentException::class, 'variadic');
    });

    it('throws when the class has no constructor', function (): void {
        $resolver = new ArgumentResolver();

        expect(fn () => $resolver->validate(new ReflectionClass(T004NoConstructor::class), ['name' => 'x']))
            ->toThrow(ArgumentException::class, 'no constructor');
    });

    it('throws when a non-closure value is given for a union typed parameter', function (): void {
        $resolver = new ArgumentResolver();

        expect(fn () => $resolver->validate(new ReflectionClass(T004Union::class), ['value' => 'x']))
            ->toThrow(ArgumentException::class, 'ambiguous');
    });

    it('throws when a literal does not match the builtin type', function (): void {
        $resolver = new ArgumentResolver();

        expect(fn () => $resolver->validate(new ReflectionClass(T004Subject::class), ['port' => '90']))
            ->toThrow(ArgumentException::class, "must be of type 'int', 'string' given");
    });

    it('throws when a class name does not satisfy the parameter type', function (): void {
        $resolver = new ArgumentResolver();

        expect(fn () => $resolver->validate(
            new ReflectionClass(T004Subject::class),
            ['contract' => T004Unrelated::class],
        ))->toThrow(ArgumentException::class, 'does not');
    });

    it('throws when null is given for a non-nullable parameter', function (): void {
        $resolver = new ArgumentResolver();

        expect(fn () => $resolver->validate(new ReflectionClass(T004Subject::class), ['contract' => null]))
            ->toThrow(ArgumentException::class, "'null' given");
    });

    it('throws when an object instance is given', function (): void {
        $resolver = new ArgumentResolver();

        try {
            $resolver->validate(new ReflectionClass(T004Subject::class), ['contract' => new T004Impl()]);
            $this->fail('Expected ArgumentException');
        } catch (ArgumentException $exception) {
            expect($exception->getMessage())->toContain('object instance')
                ->and($exception->getSuggestion())->toContain('fn () =>');
        }
    });

    it('accepts an enum case for a parameter typed with that enum', function (): void {
        $resolver = new ArgumentResolver();

        $resolver->validate(new ReflectionClass(T004EnumSubject::class), ['suit' => T004Suit::Hearts]);

        expect(true)->toBeTrue();
    });

    it('throws when an enum case of a different enum is given', function (): void {
        $resolver = new ArgumentResolver();

        try {
            $resolver->validate(new ReflectionClass(T004EnumSubject::class), ['suit' => T004Color::Red]);
            $this->fail('Expected ArgumentException');
        } catch (ArgumentException $exception) {
            expect($exception->getSuggestion())->toContain('fn () => T004Color::Red');
        }
    });
});
