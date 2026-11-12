<?php

declare(strict_types=1);

use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Exceptions\ArgumentException;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Tests\Unit\Container\Fixtures\T003Subject;
use Marko\Core\Tests\Unit\Container\Fixtures\T017Contract;
use Marko\Core\Tests\Unit\Container\Fixtures\T017Impl;
use Marko\Core\Tests\Unit\Container\Fixtures\T017Other;

it('registers a module\'s arguments when registering the module\'s bindings', function (): void {
    $container = new Container();
    $registry = new BindingRegistry($container);

    $registry->registerModule(new ModuleManifest(
        name: 'vendor/a',
        version: '1.0.0',
        source: 'vendor',
        arguments: [T003Subject::class => ['label' => 'from-a']],
    ));

    expect($container->get(T003Subject::class)->label)->toBe('from-a');
});

it('keeps working when constructed with only a container', function (): void {
    $container = new Container();
    $registry = new BindingRegistry($container);

    $registry->registerModule(new ModuleManifest(
        name: 'vendor/a',
        version: '1.0.0',
        source: 'vendor',
        bindings: [T017Contract::class => T017Impl::class],
    ));

    expect($container->get(T017Contract::class))->toBeInstanceOf(T017Impl::class);
});

it('drops a vendor module\'s arguments when an app module closure-binds the class', function (): void {
    $container = new Container();
    $registry = new BindingRegistry($container);

    $registry->registerModule(new ModuleManifest(
        name: 'vendor/a',
        version: '1.0.0',
        source: 'vendor',
        arguments: [T003Subject::class => ['label' => 'from-vendor']],
    ));
    $registry->registerModule(new ModuleManifest(
        name: 'app/b',
        version: '1.0.0',
        source: 'app',
        bindings: [T003Subject::class => fn (): T003Subject => new T003Subject('from-closure')],
    ));

    $container->validateArguments();

    expect($container->get(T003Subject::class)->label)->toBe('from-closure');
});

it('keeps arguments when a lower-priority module closure-binds the class', function (): void {
    $container = new Container();
    $registry = new BindingRegistry($container);

    $registry->registerModule(new ModuleManifest(
        name: 'app/a',
        version: '1.0.0',
        source: 'app',
        arguments: [T003Subject::class => ['label' => 'from-app']],
    ));
    $registry->registerModule(new ModuleManifest(
        name: 'vendor/b',
        version: '1.0.0',
        source: 'vendor',
        bindings: [T003Subject::class => fn (): T003Subject => new T003Subject('from-closure')],
    ));

    expect(fn () => $container->validateArguments())->toThrow(ArgumentException::class);
});

it('keeps arguments when a same-priority module closure-binds the class', function (): void {
    $container = new Container();
    $registry = new BindingRegistry($container);

    $registry->registerModule(new ModuleManifest(
        name: 'vendor/a',
        version: '1.0.0',
        source: 'vendor',
        arguments: [T003Subject::class => ['label' => 'from-a']],
    ));
    $registry->registerModule(new ModuleManifest(
        name: 'vendor/b',
        version: '1.0.0',
        source: 'vendor',
        bindings: [T003Subject::class => fn (): T003Subject => new T003Subject('from-closure')],
    ));

    expect(fn () => $container->validateArguments())->toThrow(ArgumentException::class);
});

it('keeps a vendor module\'s arguments for other classes when one class is taken over', function (): void {
    $container = new Container();
    $registry = new BindingRegistry($container);

    $registry->registerModule(new ModuleManifest(
        name: 'vendor/a',
        version: '1.0.0',
        source: 'vendor',
        arguments: [
            T003Subject::class => ['label' => 'from-vendor'],
            T017Other::class => ['name' => 'kept'],
        ],
    ));
    $registry->registerModule(new ModuleManifest(
        name: 'app/b',
        version: '1.0.0',
        source: 'app',
        bindings: [T003Subject::class => fn (): T003Subject => new T003Subject('from-closure')],
    ));

    expect($container->get(T017Other::class)->name)->toBe('kept');
});

it('drops the arguments regardless of which module registers first', function (): void {
    $container = new Container();
    $registry = new BindingRegistry($container);

    $registry->registerModule(new ModuleManifest(
        name: 'app/b',
        version: '1.0.0',
        source: 'app',
        bindings: [T003Subject::class => fn (): T003Subject => new T003Subject('from-closure')],
    ));
    $registry->registerModule(new ModuleManifest(
        name: 'vendor/a',
        version: '1.0.0',
        source: 'vendor',
        arguments: [T003Subject::class => ['label' => 'from-vendor']],
    ));

    $container->validateArguments();

    expect($container->get(T003Subject::class)->label)->toBe('from-closure');
});
