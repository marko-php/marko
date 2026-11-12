<?php

declare(strict_types=1);

use Marko\Core\Container\ArgumentRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Exceptions\ArgumentConflictException;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Tests\Unit\Container\Fixtures\T003Subject;

it('registers a module\'s arguments on the container', function (): void {
    $container = new Container();
    $registry = new ArgumentRegistry($container);

    $registry->registerModule(new ModuleManifest(
        name: 'vendor/a',
        version: '1.0.0',
        source: 'vendor',
        arguments: [T003Subject::class => ['label' => 'from-a']],
    ));

    expect($container->get(T003Subject::class)->label)->toBe('from-a');
});

it('merges different arguments of the same class from different modules', function (): void {
    $container = new Container();
    $registry = new ArgumentRegistry($container);

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
        arguments: [T003Subject::class => ['count' => 5]],
    ));

    $subject = $container->get(T003Subject::class);

    expect($subject->label)->toBe('from-a')
        ->and($subject->count)->toBe(5);
});

it('lets an app module override one argument set by a vendor module', function (): void {
    $container = new Container();
    $registry = new ArgumentRegistry($container);

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
        arguments: [T003Subject::class => ['label' => 'from-app']],
    ));

    expect($container->get(T003Subject::class)->label)->toBe('from-app');
});

it('ignores a lower-priority module\'s argument that a higher-priority module already set', function (): void {
    $container = new Container();
    $registry = new ArgumentRegistry($container);

    $registry->registerModule(new ModuleManifest(
        name: 'app/b',
        version: '1.0.0',
        source: 'app',
        arguments: [T003Subject::class => ['label' => 'from-app']],
    ));
    $registry->registerModule(new ModuleManifest(
        name: 'vendor/a',
        version: '1.0.0',
        source: 'vendor',
        arguments: [T003Subject::class => ['label' => 'from-vendor', 'count' => 3]],
    ));

    $subject = $container->get(T003Subject::class);

    expect($subject->label)->toBe('from-app')
        ->and($subject->count)->toBe(3);
});

it('throws when two modules of the same priority set the same argument of the same class', function (): void {
    $registry = new ArgumentRegistry(new Container());

    $registry->registerModule(new ModuleManifest(
        name: 'vendor/a',
        version: '1.0.0',
        source: 'vendor',
        arguments: [T003Subject::class => ['label' => 'from-a']],
    ));

    expect(fn () => $registry->registerModule(new ModuleManifest(
        name: 'vendor/b',
        version: '1.0.0',
        source: 'vendor',
        arguments: [T003Subject::class => ['label' => 'from-b']],
    )))->toThrow(ArgumentConflictException::class, 'vendor/a, vendor/b');
});

it(
    'does not throw when two modules of the same priority set different arguments of the same class',
    function (): void {
        $registry = new ArgumentRegistry(new Container());

        $registry->registerModule(new ModuleManifest(
            name: 'vendor/a',
            version: '1.0.0',
            source: 'vendor',
            arguments: [T003Subject::class => ['label' => 'from-a']],
        ));

        expect(fn () => $registry->registerModule(new ModuleManifest(
            name: 'vendor/b',
            version: '1.0.0',
            source: 'vendor',
            arguments: [T003Subject::class => ['count' => 2]],
        )))->not->toThrow(ArgumentConflictException::class);
    },
);

it('keeps the vendor module\'s other arguments when an app module overrides one', function (): void {
    $container = new Container();
    $registry = new ArgumentRegistry($container);

    $registry->registerModule(new ModuleManifest(
        name: 'vendor/a',
        version: '1.0.0',
        source: 'vendor',
        arguments: [T003Subject::class => ['label' => 'from-vendor', 'count' => 7]],
    ));
    $registry->registerModule(new ModuleManifest(
        name: 'app/b',
        version: '1.0.0',
        source: 'app',
        arguments: [T003Subject::class => ['label' => 'from-app']],
    ));

    $subject = $container->get(T003Subject::class);

    expect($subject->label)->toBe('from-app')
        ->and($subject->count)->toBe(7);
});
