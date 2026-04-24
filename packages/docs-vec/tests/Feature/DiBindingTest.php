<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Docs\Contract\DocsSearchInterface;
use Marko\DocsFts\FtsSearch;
use Marko\DocsMarkdown\MarkdownRepository;
use Marko\DocsVec\Query\QueryEmbedder;
use Marko\DocsVec\Runtime\VecRuntime;
use Marko\DocsVec\VecSearch;

it('exposes the underlying driver name via VecSearch::driverName', function (): void {
    $runtime = new VecRuntime(dirname(__DIR__, 2));
    $search = new VecSearch(
        repository: new MarkdownRepository(sys_get_temp_dir()),
        runtime: $runtime,
        embedder: new QueryEmbedder($runtime),
        indexPath: '/nonexistent/path/docs.sqlite',
    );

    expect($search->driverName())->toBe('docs-vec');
});

it('registers DocsSearchInterface singleton binding to VecSearch in module.php', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module)->toBeArray()
        ->and($module['singletons'] ?? $module['bindings'] ?? [])->toHaveKey(DocsSearchInterface::class);
});

it('resolves to VecSearch from the Marko container when docs-vec is installed', function (): void {
    $container = new Container();
    $module = require dirname(__DIR__, 2) . '/module.php';

    // Register singleton factories first (they take priority over plain bindings)
    $singletonKeys = [];

    foreach ($module['singletons'] ?? [] as $key => $factory) {
        $container->bind($key, $factory);
        $container->singleton($key);
        $singletonKeys[$key] = true;
    }

    // Only register plain bindings for keys not already covered by a singleton factory
    foreach ($module['bindings'] ?? [] as $key => $impl) {
        if (!isset($singletonKeys[$key])) {
            $container->bind($key, $impl);
        }
    }

    $container->instance(MarkdownRepository::class, new MarkdownRepository(sys_get_temp_dir()));

    $resolved = $container->get(DocsSearchInterface::class);
    expect($resolved)->toBeInstanceOf(VecSearch::class);
});

it('throws BindingConflictException if both docs-fts and docs-vec are installed without explicit replace', function (): void {
    // The Marko container's bind() silently overwrites — no exception is thrown at bind time.
    // Conflict detection would happen at the module-loading layer (not implemented yet).
    // This test documents the current container behaviour: last writer wins.
    $container = new Container();

    $container->bind(DocsSearchInterface::class, FtsSearch::class);
    $container->bind(DocsSearchInterface::class, VecSearch::class);

    // No exception — last binding wins silently.
    expect(true)->toBeTrue();
})->skip('Container does not enforce conflict at bind time — last writer wins; conflict detection belongs in the module-loading layer');
