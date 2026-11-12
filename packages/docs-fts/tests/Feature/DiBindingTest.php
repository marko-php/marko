<?php

declare(strict_types=1);

use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Exceptions\BindingException;
use Marko\Core\Module\ModuleManifest;
use Marko\Docs\Contract\DocsSearchInterface;
use Marko\DocsFts\FtsSearch;
use Marko\DocsMarkdown\MarkdownRepository;

it('binds DocsSearchInterface to FtsSearch as a shared class binding', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module['singletons'])->toBe([DocsSearchInterface::class => FtsSearch::class]);
});

it('resolves to FtsSearch from the Marko container when docs-fts is installed', function (): void {
    $container = new Container();
    $module = require dirname(__DIR__, 2) . '/module.php';

    // Pre-register MarkdownRepository since it requires a string path (non-autowirable)
    $container->instance(MarkdownRepository::class, new MarkdownRepository('/tmp'));

    new BindingRegistry($container)->registerModule(new ModuleManifest(
        name: 'marko/docs-fts',
        version: '1.0.0',
        bindings: $module['bindings'],
        singletons: $module['singletons'],
        arguments: $module['arguments'],
        source: 'vendor',
    ));

    $resolved = $container->get(DocsSearchInterface::class);

    expect($resolved)->toBeInstanceOf(FtsSearch::class);
});

it('builds FtsSearch with the packaged index path', function (): void {
    $packageRoot = dirname(__DIR__, 2);
    $module = require $packageRoot . '/module.php';
    $container = new Container();
    $container->instance(MarkdownRepository::class, new MarkdownRepository('/tmp'));
    $container->setArguments(FtsSearch::class, $module['arguments'][FtsSearch::class]);

    $search = $container->get(FtsSearch::class);
    $indexPath = new ReflectionProperty(FtsSearch::class, 'indexPath')->getValue($search);

    expect($indexPath)->toBe($packageRoot . '/resources/docs.sqlite');
});

it('throws BindingException when only marko/docs is installed without any driver', function (): void {
    $container = new Container();

    expect(fn () => $container->get(DocsSearchInterface::class))
        ->toThrow(BindingException::class);
});

it('exposes the underlying driver name via DocsSearchInterface::driverName', function (): void {
    $reflection = new ReflectionClass(DocsSearchInterface::class);

    expect($reflection->hasMethod('driverName'))->toBeTrue()
        ->and($reflection->getMethod('driverName')->getReturnType()?->getName())->toBe('string');
});
