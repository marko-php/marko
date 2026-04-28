<?php

declare(strict_types=1);

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Mcp\Protocol\JsonRpcProtocol;
use Marko\Mcp\Server\McpServer;
use Marko\Mcp\Tools\CheckConfigKeyTool;
use Marko\Mcp\Tools\FindEventObserversTool;
use Marko\Mcp\Tools\FindPluginsTargetingTool;
use Marko\Mcp\Tools\GetConfigSchemaTool;
use Marko\Mcp\Tools\ListCommandsTool;
use Marko\Mcp\Tools\ListModulesTool;
use Marko\Mcp\Tools\ListRoutesTool;
use Marko\Mcp\Tools\ResolvePreferenceTool;
use Marko\Mcp\Tools\ResolveTemplateTool;
use Marko\Mcp\Tools\Runtime\AppInfoTool;
use Marko\Mcp\Tools\ValidateModuleTool;

return [
    'bindings' => [
        McpServer::class => function (ContainerInterface $c): McpServer {
            $server = new McpServer($c->get(JsonRpcProtocol::class));
            $index = $c->get(IndexCache::class);
            $paths = $c->get(ProjectPaths::class);

            foreach ([
                CheckConfigKeyTool::class,
                FindEventObserversTool::class,
                FindPluginsTargetingTool::class,
                GetConfigSchemaTool::class,
                ListCommandsTool::class,
                ListModulesTool::class,
                ListRoutesTool::class,
                ResolvePreferenceTool::class,
                ResolveTemplateTool::class,
                ValidateModuleTool::class,
            ] as $tool) {
                $server->registerTool($tool::definition($index));
            }

            $server->registerTool(AppInfoTool::definition(
                composerJsonPath: $paths->base . '/composer.json',
                installedJsonPath: $paths->vendor . '/composer/installed.json',
            ));

            return $server;
        },
    ],
    'singletons' => [],
];
