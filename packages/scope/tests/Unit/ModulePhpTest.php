<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Scope\Context\ScopeContext;
use Marko\Scope\Exceptions\NoDriverException;
use Marko\Scope\Metadata\ScopeMetadataFactory;
use Marko\Scope\Query\ScopedOrderByFactory;
use Marko\Scope\Query\ScopeSortRendererInterface;
use Marko\Scope\Registry\PhpScopeRegistry;
use Marko\Scope\Registry\ScopeRegistryInterface;
use Marko\Scope\Resolver\ScopeResolver;

it('binds ScopeRegistryInterface to PhpScopeRegistry', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module['bindings'])->toHaveKey(ScopeRegistryInterface::class);
});

it('registers ScopeContext as a singleton', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module)->toHaveKey('singletons')
        ->and($module['singletons'])->toContain(ScopeContext::class);
});

it('registers ScopeMetadataFactory as a singleton', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module['singletons'])->toContain(ScopeMetadataFactory::class);
});

it('registers ScopeResolver as a singleton', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module['singletons'])->toContain(ScopeResolver::class);
});

it('registers ScopedOrderByFactory as a singleton', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module['singletons'])->toContain(ScopedOrderByFactory::class);
});

it('does not bind ScopeSortRendererInterface', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module['bindings'])->not->toHaveKey(ScopeSortRendererInterface::class);
});

it('throws a loud error if a ScopedOrderBy is used while no ScopeSortRendererInterface is bound', function (): void {
    $container = new Container();

    expect(fn () => $container->get(ScopeSortRendererInterface::class))
        ->toThrow(NoDriverException::class);
});

it('constructs PhpScopeRegistry from injected config repository', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';
    $factory = $module['bindings'][ScopeRegistryInterface::class];

    $config = $this->createMock(ConfigRepositoryInterface::class);
    $config->expects($this->once())
        ->method('getArray')
        ->with('scope.axes')
        ->willReturn([]);

    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->once())
        ->method('get')
        ->with(ConfigRepositoryInterface::class)
        ->willReturn($config);

    $result = $factory($container);

    expect($result)->toBeInstanceOf(PhpScopeRegistry::class);
});
