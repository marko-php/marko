<?php

declare(strict_types=1);

use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Module\ManifestParser;
use Marko\Core\Path\ProjectPaths;
use Marko\Lsp\Commands\ServeCommand;
use Marko\Lsp\Server\LspServer;

function bootLspContainer(): Container
{
    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(ProjectPaths::class, new ProjectPaths(sys_get_temp_dir()));

    $parser = new ManifestParser();
    $registry = new BindingRegistry($container);

    $registry->registerModule($parser->parse(dirname(__DIR__, 3) . '/codeindexer'));
    $registry->registerModule($parser->parse(dirname(__DIR__, 2)));

    return $container;
}

it('resolves the lsp:serve command through the container', function (): void {
    $container = bootLspContainer();

    expect($container->get(ServeCommand::class))
        ->toBeInstanceOf(ServeCommand::class);
});

it('resolves LspServer through the container', function (): void {
    $container = bootLspContainer();

    expect($container->get(LspServer::class))
        ->toBeInstanceOf(LspServer::class);
});
