<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Exceptions\ArgumentException;
use Marko\Core\Plugin\InterceptorClassGenerator;
use Marko\Core\Plugin\PluginDefinition;
use Marko\Core\Plugin\PluginInterceptedInterface;
use Marko\Core\Plugin\PluginInterceptor;
use Marko\Core\Plugin\PluginRegistry;
use Marko\Core\Tests\Unit\Container\Fixtures\T016Contract;
use Marko\Core\Tests\Unit\Container\Fixtures\T016Dep;
use Marko\Core\Tests\Unit\Container\Fixtures\T016Impl;
use Marko\Core\Tests\Unit\Container\Fixtures\T016Pluggable;
use Marko\Core\Tests\Unit\Container\Fixtures\T016PluggablePlugin;
use Marko\Core\Tests\Unit\Container\Fixtures\T016Subject;

it('autowires the parameters that have no arguments entry', function (): void {
    $container = new Container();
    $container->setArguments(T016Subject::class, ['label' => 'custom']);

    $subject = $container->get(T016Subject::class);

    expect($subject->label)->toBe('custom')
        ->and($subject->dep)->toBeInstanceOf(T016Dep::class)
        ->and($subject->count)->toBe(1);
});

it('applies arguments to a class reached through an interface binding', function (): void {
    $container = new Container();
    $container->bind(T016Contract::class, T016Impl::class);
    $container->setArguments(T016Impl::class, ['label' => 'bound']);

    $impl = $container->get(T016Contract::class);

    expect($impl)->toBeInstanceOf(T016Impl::class)
        ->and($impl->label)->toBe('bound');
});

it('overrides a builtin parameter\'s default value', function (): void {
    $container = new Container();
    $container->setArguments(T016Subject::class, ['count' => 7]);

    $subject = $container->get(T016Subject::class);

    expect($subject->count)->toBe(7)
        ->and($subject->label)->toBe('default');
});

it('throws when the parameter name does not exist on the constructor at resolve time', function (): void {
    $container = new Container();
    $container->setArguments(T016Subject::class, ['nope' => 'x']);

    expect(fn () => $container->get(T016Subject::class))->toThrow(ArgumentException::class, 'nope');
});

it('throws when resolving a closure-bound class that has arguments', function (): void {
    $container = new Container();
    $container->bind(T016Subject::class, fn (): T016Subject => new T016Subject(new T016Dep()));
    $container->setArguments(T016Subject::class, ['label' => 'custom']);

    expect(fn () => $container->get(T016Subject::class))
        ->toThrow(ArgumentException::class, T016Subject::class);
});

it('shares one instance of a singleton built with arguments', function (): void {
    $container = new Container();
    $container->singleton(T016Subject::class);
    $container->setArguments(T016Subject::class, ['label' => 'once']);

    $first = $container->get(T016Subject::class);

    expect($container->get(T016Subject::class))->toBe($first)
        ->and($first->label)->toBe('once');
});

it('still wraps the instance with the plugin interceptor', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();
    $container->setPluginInterceptor(new PluginInterceptor($container, $registry, new InterceptorClassGenerator()));
    $registry->register(new PluginDefinition(
        pluginClass: T016PluggablePlugin::class,
        targetClass: T016Pluggable::class,
        beforeMethods: ['getLabel' => ['pluginMethod' => 'beforeGetLabel', 'sortOrder' => 10]],
    ));
    $container->setArguments(T016Pluggable::class, ['label' => 'plugged']);

    $instance = $container->get(T016Pluggable::class);

    expect($instance)->toBeInstanceOf(PluginInterceptedInterface::class)
        ->and($instance->getLabel())->toBe('plugged');
});
