<?php

declare(strict_types=1);

use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Module\ManifestParser;
use Marko\Core\Path\ProjectPaths;
use Marko\Mcp\Commands\ServeCommand;
use Marko\Mcp\Server\McpServer;

function bootMcpContainer(): Container
{
    $container = new Container();
    $container->instance(ProjectPaths::class, new ProjectPaths(sys_get_temp_dir()));

    $parser = new ManifestParser();
    $registry = new BindingRegistry($container);

    $registry->registerModule($parser->parse(dirname(__DIR__, 3) . '/codeindexer'));
    $registry->registerModule($parser->parse(dirname(__DIR__, 2)));

    return $container;
}

it('resolves the mcp:serve command through the container', function (): void {
    $container = bootMcpContainer();

    expect($container->get(ServeCommand::class))
        ->toBeInstanceOf(ServeCommand::class);
});

it('resolves McpServer with all autowireable tools registered', function (): void {
    $container = bootMcpContainer();

    $server = $container->get(McpServer::class);

    expect($server)->toBeInstanceOf(McpServer::class);

    $reflection = new ReflectionClass($server);
    $toolsProp = $reflection->getProperty('tools');
    $tools = $toolsProp->getValue($server);

    expect($tools)->toBeArray()
        ->and(array_keys($tools))->toContain(
            'check_config_key',
            'find_event_observers',
            'find_plugins_targeting',
            'get_config_schema',
            'list_commands',
            'list_modules',
            'list_routes',
            'resolve_preference',
            'resolve_template',
            'validate_module',
            'app_info',
        );
});
