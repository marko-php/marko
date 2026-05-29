<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Exceptions\BindingException;
use Marko\Docs\Contract\DocsSearchInterface;
use Marko\DocsFts\FtsSearch;
use Marko\DocsMarkdown\MarkdownRepository;

it('registers DocsSearchInterface singleton binding to FtsSearch in module.php', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    // DocsSearchInterface must appear in singletons (factory closure) for proper DI wiring
    expect($module)->toHaveKey('singletons');
    expect($module['singletons'])->toHaveKey(DocsSearchInterface::class);

    $factory = $module['singletons'][DocsSearchInterface::class];

    expect($factory)->toBeInstanceOf(Closure::class);
});

it('resolves to FtsSearch from the Marko container when docs-fts is installed', function (): void {
    $container = new Container();
    $module = require dirname(__DIR__, 2) . '/module.php';

    // Pre-register MarkdownRepository since it requires a string path (non-autowirable)
    $container->instance(MarkdownRepository::class, new MarkdownRepository('/tmp'));

    foreach ($module['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    foreach ($module['singletons'] as $interface => $implementation) {
        if (is_int($interface)) {
            $container->singleton($implementation);
        } else {
            $container->bind($interface, $implementation);
            $container->singleton($interface);
        }
    }

    $resolved = $container->get(DocsSearchInterface::class);

    expect($resolved)->toBeInstanceOf(FtsSearch::class);
});

it('throws BindingException when only marko/docs is installed without any driver', function (): void {
    $container = new Container();

    expect(fn () => $container->get(DocsSearchInterface::class))
        ->toThrow(BindingException::class);
});

it('exposes the underlying driver name via DocsSearchInterface::driverName', function (): void {
    $reflection = new ReflectionClass(DocsSearchInterface::class);

    expect($reflection->hasMethod('driverName'))->toBeTrue();

    $method = $reflection->getMethod('driverName');

    expect($method->getReturnType()?->getName())->toBe('string');
});
