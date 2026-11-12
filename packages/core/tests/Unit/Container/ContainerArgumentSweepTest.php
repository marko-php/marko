<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Exceptions\ArgumentException;
use Marko\Core\Exceptions\BindingException;
use Marko\Core\Tests\Unit\Container\Fixtures\T005NoLabel;
use Marko\Core\Tests\Unit\Container\Fixtures\T005Original;
use Marko\Core\Tests\Unit\Container\Fixtures\T006Bare;
use Marko\Core\Tests\Unit\Container\Fixtures\T006Contract;
use Marko\Core\Tests\Unit\Container\Fixtures\T006Counted;
use Marko\Core\Tests\Unit\Container\Fixtures\T006Needs;
use Marko\Core\Tests\Unit\Container\Fixtures\T006Server;

it('passes when every configured argument is valid', function (): void {
    $container = new Container();
    $container->setArguments(T006Server::class, ['host' => 'example.com', 'port' => 8080]);

    $container->validateArguments();

    expect($container->resolvedInstances())->toBeEmpty();
});

it('does not instantiate any configured class', function (): void {
    T006Counted::$built = 0;
    $container = new Container();
    $container->setArguments(T006Counted::class, ['host' => 'example.com']);

    $container->validateArguments();

    expect(T006Counted::$built)->toBe(0)
        ->and($container->resolvedInstances())->toBeEmpty();
});

it('does not call closure factories', function (): void {
    $calls = 0;
    $container = new Container();
    $container->setArguments(T006Server::class, [
        'host' => function () use (&$calls): string {
            $calls++;

            return 'example.com';
        },
    ]);

    $container->validateArguments();

    expect($calls)->toBe(0);
});

it('throws when a configured class does not exist', function (): void {
    $container = new Container();
    $container->setArguments('Marko\Core\Tests\Unit\Container\Fixtures\T006Missing', ['host' => 'x']);

    expect(fn () => $container->validateArguments())
        ->toThrow(BindingException::class, 'T006Missing');
});

it('throws when the key is an interface', function (): void {
    $container = new Container();
    $container->setArguments(T006Contract::class, ['host' => 'x']);

    expect(fn () => $container->validateArguments())
        ->toThrow(ArgumentException::class, 'interface');
});

it('throws when the class has a closure binding on its own key', function (): void {
    $container = new Container();
    $container->bind(T006Server::class, fn (): T006Server => new T006Server());
    $container->setArguments(T006Server::class, ['host' => 'x']);

    expect(fn () => $container->validateArguments())
        ->toThrow(ArgumentException::class, 'closure');
});

it('throws for an unknown parameter name on a class that is never resolved', function (): void {
    $container = new Container();
    $container->setArguments(T006Server::class, ['hots' => 'x']);

    expect(fn () => $container->validateArguments())
        ->toThrow(ArgumentException::class, 'hots');
});

it('throws for a literal type mismatch on a class that is never resolved', function (): void {
    $container = new Container();
    $container->setArguments(T006Server::class, ['port' => 'not-an-int']);

    expect(fn () => $container->validateArguments())
        ->toThrow(ArgumentException::class, 'port');
});

it('throws when a class name does not satisfy the parameter type', function (): void {
    $container = new Container();
    $container->setArguments(T006Needs::class, ['contract' => T006Server::class]);

    expect(fn () => $container->validateArguments())
        ->toThrow(ArgumentException::class, 'contract');
});

it('throws when the key class is bound to a different class', function (): void {
    $container = new Container();
    $container->bind(T006Server::class, T006Counted::class);
    $container->setArguments(T006Server::class, ['host' => 'x']);

    expect(fn () => $container->validateArguments())
        ->toThrow(ArgumentException::class, T006Counted::class);
});

it('does not throw for arguments dropped by a higher-priority closure binding', function (): void {
    $container = new Container();
    $container->setArguments(T006Server::class, []);
    $container->bind(T006Server::class, fn (): T006Server => new T006Server());

    $container->validateArguments();

    expect($container->resolvedInstances())->toBeEmpty();
});

it('throws when a class with arguments but no constructor is resolved', function (): void {
    $container = new Container();
    $container->setArguments(T006Bare::class, ['host' => 'x']);

    expect(fn () => $container->get(T006Bare::class))
        ->toThrow(ArgumentException::class, 'no constructor');
});

it('validates inherited arguments against a preference replacement\'s constructor', function (): void {
    $registry = new PreferenceRegistry();
    $registry->register(T005Original::class, T005NoLabel::class);
    $container = new Container($registry);
    $container->setArguments(T005Original::class, ['label' => 'inherited']);

    expect(fn () => $container->validateArguments())
        ->toThrow(ArgumentException::class, "replaces '" . T005Original::class . "'");
});
