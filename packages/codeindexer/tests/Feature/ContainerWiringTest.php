<?php

declare(strict_types=1);

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\Commands\RebuildIndexCommand;
use Marko\CodeIndexer\Contract\IndexCacheInterface;
use Marko\CodeIndexer\Contract\ModuleWalkerInterface;
use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Module\ManifestParser;
use Marko\Core\Path\ProjectPaths;

function bootIndexerContainer(): Container
{
    $container = new Container();
    $container->instance(ProjectPaths::class, new ProjectPaths(sys_get_temp_dir()));

    $manifest = (new ManifestParser())->parse(dirname(__DIR__, 2));

    (new BindingRegistry($container))->registerModule($manifest);

    return $container;
}

it('resolves RebuildIndexCommand through the container using the package module.php', function (): void {
    $container = bootIndexerContainer();

    expect($container->get(RebuildIndexCommand::class))
        ->toBeInstanceOf(RebuildIndexCommand::class);
});

it('resolves IndexCacheInterface to a concrete IndexCache via simple binding', function (): void {
    $container = bootIndexerContainer();

    expect($container->get(IndexCacheInterface::class))
        ->toBeInstanceOf(IndexCache::class);
});

it('resolves ModuleWalkerInterface as a singleton', function (): void {
    $container = bootIndexerContainer();

    $first = $container->get(ModuleWalkerInterface::class);
    $second = $container->get(ModuleWalkerInterface::class);

    expect($first)->toBe($second);
});
