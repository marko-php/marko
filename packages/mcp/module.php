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
use Marko\Docs\Contract\DocsSearchInterface;
use Marko\Mcp\Tools\ResolveTemplateTool;
use Marko\Mcp\Tools\Runtime\Adapters\FileErrorTracker;
use Marko\Mcp\Tools\Runtime\Adapters\FileLogReader;
use Marko\Mcp\Tools\Runtime\Adapters\MarkoConsoleDispatcher;
use Marko\Mcp\Tools\Runtime\Adapters\MarkoQueryConnection;
use Marko\Mcp\Tools\Runtime\AppInfoTool;
use Marko\Mcp\Tools\Runtime\Contracts\ConsoleDispatcherInterface;
use Marko\Mcp\Tools\Runtime\Contracts\ErrorTrackerInterface;
use Marko\Mcp\Tools\Runtime\Contracts\LogReaderInterface;
use Marko\Mcp\Tools\Runtime\Contracts\QueryConnectionInterface;
use Marko\Mcp\Tools\Runtime\LastErrorTool;
use Marko\Mcp\Tools\Runtime\QueryDatabaseTool;
use Marko\Mcp\Tools\Runtime\ReadLogEntriesTool;
use Marko\Mcp\Tools\Runtime\RunConsoleCommandTool;
use Marko\Mcp\Tools\SearchDocsTool;
use Marko\Mcp\Tools\ValidateModuleTool;

return [
    'bindings' => [
        ConsoleDispatcherInterface::class => MarkoConsoleDispatcher::class,
        ErrorTrackerInterface::class => fn (ContainerInterface $c): ErrorTrackerInterface => new FileErrorTracker(
            errorFilePath: $c->get(ProjectPaths::class)->base . '/storage/last_error.json',
        ),
        LogReaderInterface::class => fn (ContainerInterface $c): LogReaderInterface => new FileLogReader(
            logsDir: $c->get(ProjectPaths::class)->base . '/storage/logs',
        ),
        QueryConnectionInterface::class => MarkoQueryConnection::class,
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

            $server->registerTool(LastErrorTool::definition(
                $c->get(ErrorTrackerInterface::class),
            ));

            $server->registerTool(ReadLogEntriesTool::definition(
                $c->get(LogReaderInterface::class),
            ));

            $server->registerTool(RunConsoleCommandTool::definition(
                $c->get(ConsoleDispatcherInterface::class),
            ));

            try {
                $server->registerTool(QueryDatabaseTool::definition(
                    $c->get(QueryConnectionInterface::class),
                ));
            } catch (\Throwable) {
                // marko/database driver not installed — query_database tool unavailable
            }

            try {
                $server->registerTool(SearchDocsTool::definition(
                    $c->get(DocsSearchInterface::class),
                ));
            } catch (\Throwable) {
                // No docs driver (docs-fts/docs-vec/etc.) installed — search_docs unavailable
            }

            return $server;
        },
    ],
    'singletons' => [],
];
