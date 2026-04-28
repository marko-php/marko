<?php

declare(strict_types=1);

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\Contract\AttributeParserInterface;
use Marko\CodeIndexer\Contract\ConfigScannerInterface;
use Marko\CodeIndexer\Contract\ModuleWalkerInterface;
use Marko\CodeIndexer\Contract\TemplateScannerInterface;
use Marko\CodeIndexer\Contract\TranslationScannerInterface;
use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Module\ManifestParser;
use Marko\Core\Path\ProjectPaths;
use Marko\DevAi\Commands\InstallCommand;
use Marko\DevAi\Commands\UpdateCommand;
use Marko\DevAi\Process\CommandRunner;
use Marko\DevAi\Process\CommandRunnerInterface;

function bootDevAiContainer(): Container
{
    $container = new Container();
    $container->instance(ProjectPaths::class, new ProjectPaths(sys_get_temp_dir()));

    $parser = new ManifestParser();
    $registry = new BindingRegistry($container);

    $registry->registerModule($parser->parse(dirname(__DIR__, 3) . '/codeindexer'));
    $registry->registerModule($parser->parse(dirname(__DIR__, 2)));

    return $container;
}

it('resolves InstallCommand through the container', function (): void {
    $container = bootDevAiContainer();

    expect($container->get(InstallCommand::class))
        ->toBeInstanceOf(InstallCommand::class);
});

it('resolves UpdateCommand through the container', function (): void {
    $container = bootDevAiContainer();

    expect($container->get(UpdateCommand::class))
        ->toBeInstanceOf(UpdateCommand::class);
});

it('binds CommandRunnerInterface to CommandRunner', function (): void {
    $container = bootDevAiContainer();

    expect($container->get(CommandRunnerInterface::class))
        ->toBeInstanceOf(CommandRunner::class);
});
